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
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class ConfigModule implements ServiceModuleInterface
{
    /** @var ConfigStorageInterface */
    private $configStorage;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(ConfigStorageInterface $configStorage, LoggerInterface $logger)
    {
        $this->configStorage = $configStorage;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        // get all configurations
        $service->get(
            '/config/common_names/',
            function (Request $request, TokenInfo $tokenInfo) {
                self::requireScope($tokenInfo, ['admin', 'portal']);

                $userId = $request->getUrl()->getQueryParameter('user_id');
                if (is_null($userId)) {
                    self::requireScope($tokenInfo, ['admin']);
                } else {
                    InputValidation::userId($userId);
                }

                $response = new JsonResponse();
                $response->setBody(
                    [
                        'items' => $this->configStorage->getAllCommonNameConfig($userId),
                    ]
                );

                return $response;
            }
        );

        // get configuration for a particular common_name
        $service->get(
            '/config/common_names/:commonName',
            function (Request $request, TokenInfo $tokenInfo, $commonName) {
                self::requireScope($tokenInfo, ['admin', 'portal']);

                InputValidation::commonName($commonName);

                $response = new JsonResponse();
                $response->setBody(
                    $this->configStorage->getCommonNameConfig($commonName)->toArray()
                );

                return $response;
            }
        );

        // set configuration for a particular common_name
        $service->put(
            '/config/common_names/:commonName',
            function (Request $request, TokenInfo $tokenInfo, $commonName) {
                self::requireScope($tokenInfo, ['admin']);

                InputValidation::commonName($commonName);

                // XXX check content type
                // XXX allow for disconnect as well when updating config

                $configData = new CommonNameConfig(Json::decode($request->getBody()));
                $this->configStorage->setCommonNameConfig($commonName, $configData);

                $response = new JsonResponse();
                $response->setBody(
                    ['ok' => true]
                );

                return $response;
            }
        );

        // get configuration for a particular user_id
        $service->get(
            '/config/users/:userId',
            function (Request $request, TokenInfo $tokenInfo, $userId) {
                self::requireScope($tokenInfo, ['admin', 'portal']);

                InputValidation::userId($userId);

                $userConfig = $this->configStorage->getUserConfig($userId);
                // we never want the OTP secret to leave the system
                $userConfig->hideOtpSecret();

                $response = new JsonResponse();
                $response->setBody(
                    $userConfig->toArray()
                );

                return $response;
            }
        );

        // set configuration for a particular user_id
        $service->put(
            '/config/users/:userId',
            function (Request $request, TokenInfo $tokenInfo, $userId) {
                self::requireScope($tokenInfo, ['admin', 'portal']);

                InputValidation::userId($userId);

                $userConfig = $this->configStorage->getUserConfig($userId);
                $newUserConfig = new UserConfig(Json::decode($request->getBody()));

                if ($userConfig->getDisable() !== $newUserConfig->getDisable()) {
                    // only 'admin' can change disable state of user
                    self::requireScope($tokenInfo, ['admin']);
                }

                if (false !== $userConfig->getOtpSecret()) {
                    // currently an OTP secret it set, the value has to be 'true'
                    // if not, admin is required to be able to update it
                    if (true !== $newUserConfig->getOtpSecret()) {
                        self::requireScope($tokenInfo, ['admin']);
                    } else {
                        // we use the old value
                        $newUserConfig->setOtpSecret($userConfig->getOtpSecret());
                    }
                }

                $this->configStorage->setUserConfig($userId, $newUserConfig);

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
