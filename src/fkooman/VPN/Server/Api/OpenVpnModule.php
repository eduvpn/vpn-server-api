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
use fkooman\VPN\Server\InputValidation;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\VPN\Server\OpenVpn\ServerManager;
use fkooman\VPN\Server\ApiResponse;

class OpenVpnModule implements ServiceModuleInterface
{
    /** @var ServerManager */
    private $serverManager;

    public function __construct(ServerManager $serverManager)
    {
        $this->serverManager = $serverManager;
    }

    public function init(Service $service)
    {
        $service->get(
            '/openvpn/connections',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);

                return new ApiResponse('connections', $this->serverManager->connections());
            }
        );

        $service->post(
            '/openvpn/kill',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);

                $commonName = $request->getPostParameter('common_name');
                InputValidation::commonName($commonName);

                return new ApiResponse('ok', $this->serverManager->kill($commonName));
            }
        );
    }
}
