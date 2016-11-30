<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Server\Api;

use Base32\Base32;
use Otp\Otp;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Server\Storage;

class UsersModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    /** @var array */
    private $groupProviders;

    public function __construct(Storage $storage, array $groupProviders)
    {
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
            '/set_totp_secret',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $totpSecret = InputValidation::totpSecret($request->getPostParameter('totp_secret'));
                $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));

                $otp = new Otp();
                if (false === $otp->checkTotp(Base32::decode($totpSecret), $totpKey)) {
                    // wrong otp key
                    return new ApiResponse('set_totp_secret', ['ok' => false]);
                }

                // XXX use DateTime here, easier for testing
                $this->storage->recordTotpKey($userId, $totpKey, time());

                return new ApiResponse('set_totp_secret', ['ok' => $this->storage->setTotpSecret($userId, $totpSecret)]);
            }
        );

        $service->post(
            '/verify_totp_key',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));

                // XXX what to do if user does not have one?
                $totpSecret = $this->storage->getTotpSecret($userId);
                $this->storage->recordTotpKey($userId, $totpKey, time());
                $otp = new Otp();

                return new ApiResponse(
                    'verify_totp_key',
                    [
                        'ok' => $otp->checkTotp(
                            Base32::decode($totpSecret),
                            $totpKey
                        ),
                    ]
                );
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

                return new ApiResponse('delete_totp_secret', ['ok' => $this->storage->deleteTotpSecret($userId)]);
            }
        );

        $service->post(
            '/set_voot_token',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $vootToken = InputValidation::vootToken($request->getPostParameter('voot_token'));

                return new ApiResponse('set_voot_token', ['ok' => $this->storage->setVootToken($userId, $vootToken)]);
            }
        );

        $service->post(
            '/delete_voot_token',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                return new ApiResponse('delete_voot_token', ['ok' => $this->storage->deleteVootToken($userId)]);
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

                return new ApiResponse('disable_user', ['ok' => $this->storage->disableUser($userId)]);
            }
        );

        $service->post(
            '/enable_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                return new ApiResponse('enable_user', ['ok' => $this->storage->enableUser($userId)]);
            }
        );

        $service->post(
            '/delete_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));

                return new ApiResponse('delete_user', ['ok' => $this->storage->deleteUser($userId)]);
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
