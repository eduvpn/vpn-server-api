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

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\ProfileConfig;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Request;

class InfoModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function init(Service $service)
    {
        $service->get(
            '/instance_config',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal', 'vpn-server-node']);

                $responseData = [
                    'instanceNumber' => $this->config->v('instanceNumber'),
                    'vpnPools' => [],
                ];

                foreach ($this->config->v('vpnPools') as $poolId => $poolConfig) {
                    $profileConfig = new ProfileConfig($poolConfig);
                    $responseData['vpnPools'][$poolId] = $profileConfig->v();
                }

                return new ApiResponse('instance_config', $responseData);
            }
        );

        $service->get(
            '/server_pool',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal', 'vpn-server-node']);
                $poolId = $request->getQueryParameter('pool_id');
                InputValidation::poolId($poolId);
                $profileConfig = new ProfileConfig($this->config->v('vpnPools', $poolId));

                return new ApiResponse('server_pool', $profileConfig->v());
            }
        );
    }
}
