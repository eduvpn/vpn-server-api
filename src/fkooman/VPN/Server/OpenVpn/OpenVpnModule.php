<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
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
namespace fkooman\VPN\Server\OpenVpn;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\VPN\Server\Utils;
use fkooman\Http\JsonResponse;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;

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
            '/status',
            function (Request $request) {
                $response = new JsonResponse();
                $response->setBody($this->serverManager->status());

                return $response;
            }
        );

        $service->get(
            '/load-stats',
            function (Request $request) {
                $response = new JsonResponse();
                $response->setBody($this->serverManager->loadStats());

                return $response;
            }
        );

        $service->get(
            '/version',
            function (Request $request) {
                $response = new JsonResponse();
                $response->setBody($this->serverManager->version());

                return $response;
            }
        );

        $service->post(
            '/kill',
            function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getPostParameter('common_name');
                Utils::validateCommonName($commonName);

                // $this->logInfo('killing cn', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName));

                $response = new JsonResponse();
                $response->setBody($this->serverManager->kill($commonName));

                return $response;
            }
        );
    }
}
