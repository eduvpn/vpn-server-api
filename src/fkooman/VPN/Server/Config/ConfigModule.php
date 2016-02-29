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

namespace fkooman\VPN\Server\Config;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use fkooman\VPN\Server\InputValidation;
use fkooman\Json\Json;

class ConfigModule implements ServiceModuleInterface
{
    /** @var StaticConfig */
    private $staticConfig;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(StaticConfig $staticConfig, LoggerInterface $logger)
    {
        $this->staticConfig = $staticConfig;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        // get all configurations
        $service->get(
            '/config/',
            function (Request $request) {
                $userId = InputValidation::userId(
                    $request->getUrl()->getQueryParameter('user_id'),
                    false // OPTIONAL
                );
                $response = new JsonResponse();
                $response->setBody(
                    [
                        'items' => $this->staticConfig->getAllConfig($userId),
                    ]
                );

                return $response;
            }
        );

        // get configuration for a particular common_name
        $service->get(
            '/config/:commonName',
            function (Request $request, $commonName) {
                $commonName = InputValidation::commonName(
                    $commonName,
                    true // REQUIRED
                );

                $response = new JsonResponse();
                $response->setBody(
                    $this->staticConfig->getConfig($commonName)->toArray()
                );

                return $response;
            }
        );

        // set configuration for a particular common_name
        $service->put(
            '/config/:commonName',
            function (Request $request, $commonName) {
                // XXX check content type
                $commonName = InputValidation::commonName(
                    $commonName,
                    true // REQUIRED
                );

                // XXX: Config object validates input (move this?!)
                $config = new Config(Json::decode($request->getBody()));
                $this->staticConfig->setConfig($commonName, $config);

                $response = new JsonResponse();
                $response->setBody(
                    ['ok' => true]
                );

                return $response;
            }
        );
    }
}
