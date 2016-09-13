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
            function (array $serverData, array $getData, array $postData, array $hookData) {
                Utils::requireUser($hookData, ['admin', 'portal']);

                return $this->getInfo();
            }
        );
    }

    private function getInfo()
    {
        $responseData = [];
        foreach ($this->instanceConfig->pools() as $poolId) {
            $responseData[$poolId] = $this->instanceConfig->pool($poolId)->toArray();
        }

        return new ApiResponse('pools', $responseData);
    }
}
