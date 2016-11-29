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

use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Server\Storage;

class UsersModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
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

                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);
                $totpSecret = $request->getPostParameter('totp_secret');
                InputValidation::totpSecret($totpSecret);

                return new ApiResponse('set_totp_secret', ['ok' => $this->storage->setTotpSecret($userId, $totpSecret)]);
            }
        );

        $service->post(
            '/delete_totp_secret',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);

                return new ApiResponse('delete_totp_secret', ['ok' => $this->storage->deleteTotpSecret($userId)]);
            }
        );

        $service->post(
            '/set_voot_token',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);
                $vootToken = $request->getPostParameter('voot_token');
                InputValidation::vootToken($vootToken);

                return new ApiResponse('set_voot_token', ['ok' => $this->storage->setVootToken($userId, $vootToken)]);
            }
        );

        $service->post(
            '/delete_voot_token',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);

                return new ApiResponse('delete_voot_token', ['ok' => $this->storage->deleteVootToken($userId)]);
            }
        );

        $service->post(
            '/disable_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);

                return new ApiResponse('disable_user', ['ok' => $this->storage->disableUser($userId)]);
            }
        );

        $service->post(
            '/enable_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);

                return new ApiResponse('enable_user', ['ok' => $this->storage->enableUser($userId)]);
            }
        );

        $service->post(
            '/delete_user',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $userId = $request->getPostParameter('user_id');
                InputValidation::userId($userId);

                return new ApiResponse('delete_user', ['ok' => $this->storage->deleteUser($userId)]);
            }
        );
    }
}
