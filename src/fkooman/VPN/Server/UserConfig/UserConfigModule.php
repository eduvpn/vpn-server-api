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

namespace fkooman\VPN\Server\UserConfig;

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
use fkooman\Http\Exception\BadRequestException;

class UserConfigModule implements ServiceModuleInterface
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
        $service->get(
            '/config/users',
            function (Request $request, TokenInfo $tokenInfo) {
                Utils::requireScope($tokenInfo, ['admin']);

                $userConfigArray = [];
                foreach ($this->io->readFolder($this->configDir) as $configFile) {
                    $userConfig = new UserConfig(
                        Json::decode(
                            $this->io->readFile($configFile)
                        )
                    );
                    // never expose the OTP secret
                    $userConfig->hideOtpSecret();
                    $userConfigArray[basename($configFile)] = $userConfig->toArray();
                }

                $response = new JsonResponse();
                $response->setBody($userConfigArray);

                return $response;
            }
        );

        $service->get(
            '/config/users/:userId',
            function (Request $request, TokenInfo $tokenInfo, $userId) {
                Utils::requireScope($tokenInfo, ['admin', 'portal']);
                InputValidation::userId($userId);

                $fileName = sprintf('%s/%s', $this->configDir, $userId);
                if (!$this->io->isFile($fileName)) {
                    // if the file does not exist, use default values
                    $userConfig = new UserConfig([]);
                } else {
                    $userConfig = new UserConfig(
                        Json::decode(
                            $this->io->readFile(
                                sprintf('%s/%s', $this->configDir, $userId)
                            )
                        )
                    );
                    // never expose the OTP secret
                    $userConfig->hideOtpSecret();
                }

                $response = new JsonResponse();
                $response->setBody($userConfig->toArray());

                return $response;
            }
        );

        $service->put(
            '/config/users/:userId/otp_secret',
            function (Request $request, TokenInfo $tokenInfo, $userId) {
                Utils::requireScope($tokenInfo, ['admin', 'portal']);
                InputValidation::userId($userId);

                $fileName = sprintf('%s/%s', $this->configDir, $userId);
                if (!$this->io->isFile($fileName)) {
                    // if the file does not exist, use default values
                    $userConfig = new UserConfig([]);
                } else {
                    $userConfig = new UserConfig(
                        Json::decode(
                            $this->io->readFile(
                                sprintf('%s/%s', $this->configDir, $userId)
                            )
                        )
                    );
                    // never expose the OTP secret
                    $userConfig->hideOtpSecret();
                }

                if (false !== $userConfig->getOtpSecret()) {
                    // an OTP secret was already set, it is not allowed to
                    // update the otp_secret using this API call
                    throw new BadRequestException('otp_secret already set');
                }

                // we wrap the request body in an UserConfig object to validate
                // whatever is there
                $requestUserConfig = new UserConfig(Json::decode($request->getBody()));

                // we extract the OTP secret from the request body and set it
                $userConfig->setOtpSecret($requestUserConfig->getOtpSecret());

                $this->io->writeFile(
                    sprintf('%s/%s', $this->configDir, $userId),
                    Json::encode($userConfig->toArray())
                );

                $response = new JsonResponse();
                $response->setBody(['ok' => true]);

                return $response;
            }
        );

        $service->put(
            '/config/users/:userId',
            function (Request $request, TokenInfo $tokenInfo, $userId) {
                Utils::requireScope($tokenInfo, ['admin']);
                InputValidation::userId($userId);

                // we wrap the request body in an UserConfig object to validate
                // whatever is there
                $requestUserConfig = new UserConfig(Json::decode($request->getBody()));

                $this->io->writeFile(
                    sprintf('%s/%s', $this->configDir, $userId),
                    Json::encode($requestUserConfig->toArray())
                );

                $response = new JsonResponse();
                $response->setBody(['ok' => true]);

                return $response;
            }
        );
    }
}
