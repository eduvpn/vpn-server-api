<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Api;

use DateTime;
use fkooman\Otp\Exception\OtpException;
use fkooman\Otp\Totp;
use LC\Common\Config;
use LC\Common\Http\ApiErrorResponse;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\InputValidation;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Server\Storage;

class UsersModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Server\Storage */
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
        $service->get(
            '/user_list',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                return new ApiResponse('user_list', $this->storage->getUsers());
            }
        );

        $service->post(
            '/set_totp_secret',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));
                $totpSecret = InputValidation::totpSecret($request->getPostParameter('totp_secret'));

                // check if there is already a TOTP secret registered for this user
                if (false !== $this->storage->getOtpSecret($userId)) {
                    return new ApiErrorResponse('set_totp_secret', 'TOTP secret already set');
                }

                $totp = new Totp($this->storage);
                try {
                    $totp->register($userId, $totpSecret, $totpKey);
                    $this->storage->addUserMessage($userId, 'notification', 'TOTP secret registered');

                    return new ApiResponse('set_totp_secret');
                } catch (OtpException $e) {
                    $msg = sprintf('TOTP registration failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userId, 'notification', $msg);

                    return new ApiErrorResponse('set_totp_secret', $msg);
                }
            }
        );

        $service->post(
            '/verify_totp_key',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));

                $totp = new Totp($this->storage);
                try {
                    $totp->verify($userId, $totpKey);

                    return new ApiResponse('verify_totp_key');
                } catch (OtpException $e) {
                    $msg = sprintf('TOTP validation failed: %s', $e->getMessage());
                    $this->storage->addUserMessage($userId, 'notification', $msg);

                    return new ApiErrorResponse('verify_totp_key', $msg);
                }
            }
        );

        $service->get(
            '/has_totp_secret',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('has_totp_secret', false !== $this->storage->getOtpSecret($userId));
            }
        );

        $service->post(
            '/delete_totp_secret',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                $this->storage->deleteOtpSecret($userId);
                $this->storage->addUserMessage($userId, 'notification', 'TOTP secret deleted');

                return new ApiResponse('delete_totp_secret');
            }
        );

        $service->get(
            '/is_disabled_user',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('is_disabled_user', $this->storage->isDisabledUser($userId));
            }
        );

        $service->post(
            '/disable_user',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                $this->storage->disableUser($userId);
                $this->storage->addUserMessage($userId, 'notification', 'account disabled');

                return new ApiResponse('disable_user');
            }
        );

        $service->post(
            '/enable_user',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                $this->storage->enableUser($userId);
                $this->storage->addUserMessage($userId, 'notification', 'account (re)enabled');

                return new ApiResponse('enable_user');
            }
        );

        $service->post(
            '/delete_user',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $this->storage->deleteUser($userId);

                return new ApiResponse('delete_user');
            }
        );

        $service->get(
            '/user_session_expires_at',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);
                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('user_session_expires_at', $this->storage->getSessionExpiresAt($userId));
            }
        );

        $service->get(
            '/user_permission_list',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);
                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('user_permission_list', $this->storage->getPermissionList($userId));
            }
        );

        $service->post(
            '/user_update_session_info',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $permissionList = InputValidation::permissionList($request->getPostParameter('permission_list'));
                $sessionExpiresAt = new DateTime($request->getPostParameter('session_expires_at'));
                // XXX make sure it is in the future!
                $this->storage->updateSessionInfo($userId, $sessionExpiresAt, $permissionList);

                return new ApiResponse('user_update_session_info');
            }
        );
    }
}
