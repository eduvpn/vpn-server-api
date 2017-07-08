<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Api;

use DateTime;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\ApiErrorResponse;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\ProfileConfig;
use SURFnet\VPN\Server\Exception\TotpException;
use SURFnet\VPN\Server\Exception\YubiKeyException;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\Totp;
use SURFnet\VPN\Server\YubiKey;

class ConnectionsModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    /** @var array */
    private $groupProviders;

    public function __construct(Config $config, Storage $storage, array $groupProviders)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->groupProviders = $groupProviders;
    }

    public function init(Service $service)
    {
        $service->post(
            '/connect',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->connect($request);
            }
        );

        $service->post(
            '/disconnect',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->disconnect($request);
            }
        );

        $service->post(
            '/verify_two_factor',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->verifyTwoFactor($request);
            }
        );
    }

    public function connect(Request $request)
    {
        $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->getPostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->getPostParameter('ip6'));
        $connectedAt = InputValidation::connectedAt($request->getPostParameter('connected_at'));

        if (true !== $response = $this->verifyConnection($profileId, $commonName)) {
            return $response;
        }

        $this->storage->clientConnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)));

        return new ApiResponse('connect');
    }

    public function disconnect(Request $request)
    {
        $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->getPostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->getPostParameter('ip6'));

        $connectedAt = InputValidation::connectedAt($request->getPostParameter('connected_at'));
        $disconnectedAt = InputValidation::disconnectedAt($request->getPostParameter('disconnected_at'));
        $bytesTransferred = InputValidation::bytesTransferred($request->getPostParameter('bytes_transferred'));

        $this->storage->clientDisconnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)), new DateTime(sprintf('@%d', $disconnectedAt)), $bytesTransferred);

        return new ApiResponse('disconnect');
    }

    public function verifyTwoFactor(Request $request)
    {
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $twoFactorType = InputValidation::twoFactorType($request->getPostParameter('two_factor_type'));

        $certInfo = $this->storage->getUserCertificateInfo($commonName);
        $userId = $certInfo['user_id'];

        switch ($twoFactorType) {
            case 'yubi':
                $yubiKeyOtp = InputValidation::yubiKeyOtp($request->getPostParameter('two_factor_value'));
                // XXX make sure user has a yubiKeyId first!
                $yubiKeyId = $this->storage->getYubiKeyId($userId);
                try {
                    $yubiKey = new YubiKey();
                    $yubiKey->verify($userId, $yubiKeyOtp, $yubiKeyId);
                } catch (YubiKeyException $e) {
                    $this->storage->addUserMessage($userId, 'notification', sprintf('[VPN] YubiKey OTP validation failed: %s', $e->getMessage()));

                    return new ApiErrorResponse('verify_two_factor', $e->getMessage());
                }
                break;
            case 'totp':
                $totpKey = InputValidation::totpKey($request->getPostParameter('two_factor_value'));
                try {
                    $totp = new Totp($this->storage);
                    $totp->verify($userId, $totpKey);
                } catch (TotpException $e) {
                    $this->storage->addUserMessage($userId, 'notification', sprintf('[VPN] TOTP validation failed: %s', $e->getMessage()));

                    return new ApiErrorResponse('verify_two_factor', $e->getMessage());
                }
                break;
            default:
                return new ApiErrorResponse('verify_two_factor', 'invalid two factor type');
        }

        return new ApiResponse('verify_two_factor');
    }

    private function verifyConnection($profileId, $commonName)
    {
        // verify status of certificate/user
        if (false === $result = $this->storage->getUserCertificateInfo($commonName)) {
            // if a certificate does no longer exist, we cannot figure out the user
            return new ApiErrorResponse('connect', 'user or certificate does not exist');
        }

        if ($result['user_is_disabled']) {
            $msg = '[VPN] unable to connect, account is disabled';
            $this->storage->addUserMessage($result['user_id'], 'notification', $msg);

            return new ApiErrorResponse('connect', $msg);
        }

        if ($result['certificate_is_disabled']) {
            $msg = sprintf('[VPN] unable to connect, certificate "%s" is disabled', $result['display_name']);
            $this->storage->addUserMessage($result['user_id'], 'notification', $msg);

            return new ApiErrorResponse('connect', $msg);
        }

        return $this->verifyAcl($profileId, $result['user_id']);
    }

    private function verifyAcl($profileId, $externalUserId)
    {
        // verify ACL
        $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
        if ($profileConfig->getItem('enableAcl')) {
            // ACL enabled
            $userGroups = [];
            foreach ($this->groupProviders as $groupProvider) {
                $userGroups = array_merge($userGroups, $groupProvider->getGroups($externalUserId));
            }

            if (false === self::isMember($userGroups, $profileConfig->getSection('aclGroupList')->toArray())) {
                $msg = '[VPN] unable to connect, account not a member of required group';
                $this->storage->addUserMessage($externalUserId, 'notification', $msg);

                return new ApiErrorResponse('connect', $msg);
            }
        }

        return true;
    }

    private static function isMember(array $memberOf, array $aclGroupList)
    {
        //        var_dump($aclGroupList);
        // one of the groups must be listed in the profile ACL list
        foreach ($memberOf as $memberGroup) {
            if (in_array($memberGroup['id'], $aclGroupList)) {
                return true;
            }
        }

        return false;
    }
}
