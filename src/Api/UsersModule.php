<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Api;

use fkooman\OAuth\Client\AccessToken;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\ApiErrorResponse;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Server\Exception\TotpException;
use SURFnet\VPN\Server\Exception\YubiKeyException;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\Totp;
use SURFnet\VPN\Server\YubiKey;

class UsersModule implements ServiceModuleInterface
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
        $service->get(
            '/user_list',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                return new ApiResponse('user_list', $this->storage->getUsers());
            }
        );

        $service->post(
            '/set_yubi_key_id',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $yubiKeyOtp = InputValidation::yubiKeyOtp($request->getPostParameter('yubi_key_otp'));

                // check if there is already a YubiKey ID registered for this user
                if ($this->storage->hasYubiKeyId($userId)) {
                    return new ApiErrorResponse('set_yubi_key_id', 'YubiKey ID already set');
                }

                $yubiKey = new YubiKey();
                try {
                    $yubiKeyId = $yubiKey->verify($userId, $yubiKeyOtp);
                    $this->storage->setYubiKeyId($userId, $yubiKeyId);
                    $this->storage->addUserMessage($userId, 'notification', sprintf('YubiKey ID "%s" registered', $yubiKeyId));

                    return new ApiResponse('set_yubi_key_id');
                } catch (YubiKeyException $e) {
                    $msg = sprintf('YubiKey OTP verification failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userId, 'notification', $msg);

                    return new ApiErrorResponse('set_yubi_key_id', $msg);
                }
            }
        );

        $service->post(
            '/verify_yubi_key_otp',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $yubiKeyOtp = InputValidation::yubiKeyOtp($request->getPostParameter('yubi_key_otp'));
                $yubiKeyId = $this->storage->getYubiKeyId($userId);

                // XXX make sure we have a registered yubiKeyID first?!

                $yubiKey = new YubiKey();
                try {
                    $yubiKey->verify($userId, $yubiKeyOtp, $yubiKeyId);

                    return new ApiResponse('verify_yubi_key_otp');
                } catch (YubiKeyException $e) {
                    $msg = sprintf('YubiKey OTP verification failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userId, 'notification', $msg);

                    return new ApiErrorResponse('verify_yubi_key_otp', $msg);
                }
            }
        );

        $service->get(
            '/has_yubi_key_id',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('has_yubi_key_id', $this->storage->hasYubiKeyId($userId));
            }
        );

        $service->get(
            '/yubi_key_id',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                $yubiKeyId = $this->storage->getYubiKeyId($userId);
                if (is_null($yubiKeyId)) {
                    return new ApiResponse('yubi_key_id', false);
                }

                return new ApiResponse('yubi_key_id', $yubiKeyId);
            }
        );

        $service->post(
            '/delete_yubi_key_id',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                $yubiKeyId = $this->storage->getYubiKeyId($userId);
                $this->storage->deleteYubiKeyId($userId);
                $this->storage->addUserMessage($userId, 'notification', sprintf('YubiKey ID "%s" deleted', $yubiKeyId));

                return new ApiResponse('delete_yubi_key_id');
            }
        );

        $service->post(
            '/set_totp_secret',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));
                $totpSecret = InputValidation::totpSecret($request->getPostParameter('totp_secret'));

                // check if there is already a TOTP secret registered for this user
                if ($this->storage->hasTotpSecret($userId)) {
                    return new ApiErrorResponse('set_totp_secret', 'TOTP secret already set');
                }

                $totp = new Totp($this->storage);
                try {
                    $totp->verify($userId, $totpKey, $totpSecret);
                } catch (TotpException $e) {
                    $msg = sprintf('TOTP verification failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userId, 'notification', $msg);

                    return new ApiErrorResponse('set_totp_secret', $msg);
                }

                $this->storage->setTotpSecret($userId, $totpSecret);
                $this->storage->addUserMessage($userId, 'notification', 'TOTP secret registered');

                return new ApiResponse('set_totp_secret');
            }
        );

        $service->post(
            '/verify_totp_key',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));

                $totp = new Totp($this->storage);
                try {
                    $totp->verify($userId, $totpKey);
                } catch (TotpException $e) {
                    $msg = sprintf('TOTP validation failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userId, 'notification', $msg);

                    return new ApiErrorResponse('verify_totp_key', $msg);
                }

                return new ApiResponse('verify_totp_key');
            }
        );

        $service->get(
            '/has_totp_secret',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('has_totp_secret', $this->storage->hasTotpSecret($userId));
            }
        );

        $service->post(
            '/delete_totp_secret',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                $this->storage->deleteTotpSecret($userId);
                $this->storage->addUserMessage($userId, 'notification', 'TOTP secret deleted');

                return new ApiResponse('delete_totp_secret');
            }
        );

        $service->post(
            '/set_voot_token',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                // load the POSTed AccessToken from JSON
                $vootToken = AccessToken::fromJson($request->getPostParameter('voot_token'));
                $this->storage->setVootToken($userId, $vootToken);

                return new ApiResponse('set_voot_token');
            }
        );

        $service->post(
            '/delete_voot_token',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $this->storage->deleteVootToken($userId);

                return new ApiResponse('delete_voot_token');
            }
        );

        $service->get(
            '/has_voot_token',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('has_voot_token', $this->storage->hasVootToken($userId));
            }
        );

        $service->get(
            '/is_disabled_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('is_disabled_user', $this->storage->isDisabledUser($userId));
            }
        );

        $service->post(
            '/disable_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                $this->storage->disableUser($userId);
                $this->storage->addUserMessage($userId, 'notification', 'account disabled');

                return new ApiResponse('disable_user');
            }
        );

        $service->post(
            '/enable_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                $this->storage->enableUser($userId);
                $this->storage->addUserMessage($userId, 'notification', 'account (re)enabled');

                return new ApiResponse('enable_user');
            }
        );

        $service->post(
            '/delete_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $this->storage->deleteUser($userId);

                return new ApiResponse('delete_user');
            }
        );

        $service->get(
            '/user_groups',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = $request->getQueryParameter('user_id');

                $userGroups = [];
                foreach ($this->groupProviders as $groupProvider) {
                    $userGroups = array_merge($userGroups, $groupProvider->getGroups($userId));
                }

                return new ApiResponse('user_groups', $userGroups);
            }
        );
    }
}
