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

use SURFnet\VPN\Server\InstanceConfig;
use SURFnet\VPN\Server\PoolConfig;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Request;

class InfoModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\InstanceConfig */
    private $instanceConfig;

    public function __construct(InstanceConfig $instanceConfig)
    {
        $this->instanceConfig = $instanceConfig;
    }

    public function init(Service $service)
    {
        $service->get(
            '/info/server',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal']);

                return $this->getInfo();
            }
        );
    }

    private function getInfo()
    {
        $responseData = [];
        foreach (array_keys($this->instanceConfig->v('vpnPools')) as $poolId) {
            $poolConfig = new PoolConfig($this->instanceConfig->v('vpnPools', $poolId));
            $responseData[$poolId] = $poolConfig->v();
        }

        return new ApiResponse('pools', $responseData);
    }
}
