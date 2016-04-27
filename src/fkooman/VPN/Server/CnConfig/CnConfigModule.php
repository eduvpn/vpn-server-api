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

namespace fkooman\VPN\Server\CnConfig;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use fkooman\VPN\Server\InputValidation;
use fkooman\Json\Json;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\IO\IOInterface;
use fkooman\VPN\Server\Utils;

class CnConfigModule implements ServiceModuleInterface
{
    /** @var string */
    private $configDir;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \fkooman\IO\IOInterface */
    private $io;

    public function __construct($configDir, LoggerInterface $logger, IOInterface $io)
    {
        $this->configDir = $configDir;
        $this->logger = $logger;
        $this->io = $io;
    }

    public function init(Service $service)
    {
        // GET /config/common_names               get all CNs
        // GET /config/common_names?user_id=foo   get all CNs, but only for one user
        $service->get(
            '/config/common_names',
            function (Request $request, TokenInfo $tokenInfo) {
                Utils::requireScope($tokenInfo, ['admin', 'portal']);
                $userId = $request->getUrl()->getQueryParameter('user_id');
                if (!is_null($userId)) {
                    InputValidation::userId($userId);
                    // filter the directory list if user_id was specified
                    $fileFilter = sprintf('%s_*', $userId);
                } else {
                    // only admin is allow to request all CNs for all users
                    Utils::requireScope($tokenInfo, ['admin']);
                    $fileFilter = '*';
                }

                $cnConfigArray = [];
                foreach ($this->io->readFolder($this->configDir, $fileFilter) as $configFile) {
                    $cnConfig = new CnConfig(
                        Json::decode(
                            $this->io->readFile($configFile)
                        )
                    );
                    $cnConfigArray[basename($configFile)] = $cnConfig->toArray();
                }

                $response = new JsonResponse();
                $response->setBody(['items' => $cnConfigArray]);

                return $response;
            }
        );

        // GET /config/common_names/:commonName   get a particular CN        
        $service->get(
            '/config/common_names/:commonName',
            function (Request $request, TokenInfo $tokenInfo, $commonName) {
                Utils::requireScope($tokenInfo, ['admin']);
                InputValidation::commonName($commonName);

                $fileName = sprintf('%s/%s', $this->configDir, $commonName);
                if (!$this->io->isFile($fileName)) {
                    // if the file does not exist, use default values
                    $cnConfig = new CnConfig([]);
                } else {
                    $cnConfig = new CnConfig(
                        Json::decode(
                            $this->io->readFile(
                                sprintf('%s/%s', $this->configDir, $commonName)
                            )
                        )
                    );
                }

                $response = new JsonResponse();
                $response->setBody($cnConfig->toArray());

                return $response;
            }
        );

        // PUT /config/common_names/:commonName   set new config for CN
        $service->put(
            '/config/common_names/:commonName',
            function (Request $request, TokenInfo $tokenInfo, $commonName) {
                Utils::requireScope($tokenInfo, ['admin']);
                InputValidation::commonName($commonName);

                // we wrap the request body in an CnConfig object to validate
                // whatever is there
                $requestCnConfig = new CnConfig(Json::decode($request->getBody()));

                $this->io->writeFile(
                    sprintf('%s/%s', $this->configDir, $commonName),
                    Json::encode($requestCnConfig->toArray()),
                    true
                );

                $response = new JsonResponse();
                $response->setBody(['ok' => true]);

                return $response;
            }
        );
    }
}
