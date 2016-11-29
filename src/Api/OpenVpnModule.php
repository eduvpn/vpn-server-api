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

use SURFnet\VPN\Server\OpenVpn\ServerManager;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\AuthUtils;

class OpenVpnModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\OpenVpn\ServerManager */
    private $serverManager;

    public function __construct(ServerManager $serverManager)
    {
        $this->serverManager = $serverManager;
    }

    public function init(Service $service)
    {
        $service->get(
            '/client_connections',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                return new ApiResponse('client_connections', $this->serverManager->connections());
            }
        );

        $service->post(
            '/kill_client',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal']);

                $commonName = $request->getPostParameter('common_name');
                InputValidation::commonName($commonName);

                return new ApiResponse('kill_client', $this->serverManager->kill($commonName));
            }
        );
    }
}
