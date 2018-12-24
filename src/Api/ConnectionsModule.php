<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
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
use SURFnet\VPN\Server\Storage;

class ConnectionsModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    public function __construct(Config $config, Storage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/connect',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->connect($request);
            }
        );

        $service->post(
            '/disconnect',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->disconnect($request);
            }
        );
    }

    /**
     * @return \SURFnet\VPN\Common\Http\Response
     */
    public function connect(Request $request)
    {
        $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->getPostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->getPostParameter('ip6'));
        $connectedAt = InputValidation::connectedAt($request->getPostParameter('connected_at'));

        if (null !== $response = $this->verifyConnection($profileId, $commonName)) {
            return $response;
        }

        $this->storage->clientConnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)));

        return new ApiResponse('connect');
    }

    /**
     * @return \SURFnet\VPN\Common\Http\Response
     */
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

    /**
     * @param string $profileId
     * @param string $commonName
     *
     * @return null|\SURFnet\VPN\Common\Http\ApiErrorResponse
     */
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

        return $this->verifyAcl($profileId, $result['user_id']);
    }

    /**
     * @param string $profileId
     * @param string $externalUserId
     *
     * @return null|\SURFnet\VPN\Common\Http\ApiErrorResponse
     */
    private function verifyAcl($profileId, $externalUserId)
    {
        // verify ACL
        $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
        if ($profileConfig->getItem('enableAcl')) {
            // ACL enabled
            $userGroups = $this->storage->getEntitlementList($externalUserId);
            if (false === self::isMember($userGroups, $profileConfig->getSection('aclGroupList')->toArray())) {
                $msg = '[VPN] unable to connect, account not a member of required group';
                $this->storage->addUserMessage($externalUserId, 'notification', $msg);

                return new ApiErrorResponse('connect', $msg);
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    private static function isMember(array $memberOf, array $aclGroupList)
    {
        //        var_dump($aclGroupList);
        // one of the groups must be listed in the profile ACL list
        foreach ($memberOf as $memberGroup) {
            if (\in_array($memberGroup, $aclGroupList, true)) {
                return true;
            }
        }

        return false;
    }
}
