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
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class ConfigModule implements ServiceModuleInterface
{
    /** @var ConfigStorageInterface */
    private $configStorage;

    /** @var array */
    private $allowedPools;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(ConfigStorageInterface $configStorage, array $allowedPools, LoggerInterface $logger)
    {
        $this->configStorage = $configStorage;
        $this->allowedPools = $allowedPools;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        // get all configurations
        $service->get(
            '/config/',
            function (Request $request, TokenInfo $tokenInfo) {
                $userId = $request->getUrl()->getQueryParameter('user_id');
                if (!is_null($userId)) {
                    self::requireScope($tokenInfo, ['config_get', 'config_get_user']);
                    InputValidation::userId($userId);
                } else {
                    self::requireScope($tokenInfo, ['config_get']);
                }

                $response = new JsonResponse();
                $response->setBody(
                    [
                        'items' => $this->configStorage->getAllConfig($userId),
                    ]
                );

                return $response;
            }
        );

        // get configuration for a particular common_name
        $service->get(
            '/config/:commonName',
            function (Request $request, TokenInfo $tokenInfo, $commonName) {
                self::requireScope($tokenInfo, ['config_get']);

                InputValidation::commonName($commonName);

                $response = new JsonResponse();
                $response->setBody(
                    $this->configStorage->getConfig($commonName)->toArray()
                );

                return $response;
            }
        );

        // set configuration for a particular common_name
        $service->put(
            '/config/:commonName',
            function (Request $request, TokenInfo $tokenInfo, $commonName) {
                self::requireScope($tokenInfo, ['config_update']);

                // XXX check content type
                // XXX allow for disconnect as well when updating config

                InputValidation::commonName($commonName);

                $configData = new ConfigData(Json::decode($request->getBody()));
                if (!in_array($configData->getPool(), $this->allowedPools)) {
                    throw new BadRequestException('invalid "pool"');
                }
                $this->configStorage->setConfig($commonName, $configData);

                $response = new JsonResponse();
                $response->setBody(
                    ['ok' => true]
                );

                return $response;
            }
        );
    }

    private static function requireScope(TokenInfo $tokenInfo, array $requiredScope)
    {
        foreach ($requiredScope as $s) {
            if ($tokenInfo->getScope()->hasScope($s)) {
                return;
            }
        }

        throw new ForbiddenException('insufficient_scope', sprintf('"%s" scope required', implode(',', $requiredScope)));
    }
}
