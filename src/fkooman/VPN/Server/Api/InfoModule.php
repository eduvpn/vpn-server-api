<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace fkooman\VPN\Server\Api;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\VPN\Server\Pools;
use fkooman\VPN\Server\ApiResponse;

class InfoModule implements ServiceModuleInterface
{
    /** @var \fkooman\VPN\Server\Pools */
    private $pools;

    public function __construct(Pools $pools)
    {
        $this->pools = $pools;
    }

    public function init(Service $service)
    {
        $service->get(
            '/info/server',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);

                return $this->getInfo();
            }
        );
    }

    private function getInfo()
    {
        $data = [];
        foreach ($this->pools as $pool) {
            $data[] = $pool->toArray();
        }

        return new ApiResponse('pools', $data);
    }
}
