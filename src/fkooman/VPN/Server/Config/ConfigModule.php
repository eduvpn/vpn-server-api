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
        $service->get(
            '/static/ip',
           function (Request $request) {
                // we typically deal with CNs, not user IDs, but the part of 
                // the CN before the first '_' is considered the user ID
                $userId = InputValidation::userId(
                    $request->getUrl()->getQueryParameter('user_id'),
                    false // OPTIONAL
                );
                $commonName = InputValidation::commonName(
                    $request->getUrl()->getQueryParameter('common_name'),
                    false // OPTIONAL
                );

                if (!is_null($userId)) {
                    // per user
                    $ipConfig = $this->staticConfig->getStaticAddresses($userId);
                } elseif (!is_null($commonName)) {
                    // per CN
                    $ipConfig = $this->staticConfig->getStaticAddress($commonName);
                } else {
                    // all
                    $ipConfig = $this->staticConfig->getStaticAddresses();
                }

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => true,
                        'ip' => $ipConfig,
                        'ipRange' => $this->staticConfig->getIpRange()->getRange(),
                        'poolRange' => $this->staticConfig->getPoolRange()->getRange(),
                    )
                );

                return $response;
            }
        );

        $service->post(
            '/static/ip',
           function (Request $request) {
                $commonName = InputValidation::commonName(
                    $request->getPostParameter('common_name'),
                    true // REQUIRED
                );
                $v4 = InputValidation::v4(
                    $request->getPostParameter('v4'),
                    false // OPTIONAL
                );

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->staticConfig->setStaticAddresses($commonName, $v4),
                    )
                );

                $this->logger->info('setting static IP', array('cn' => $commonName, 'v4' => $v4));

                return $response;
            }
        );

        $service->post(
            '/ccd/disable',
            function (Request $request) {
                $commonName = InputValidation::commonName(
                    $request->getPostParameter('common_name'),
                    true // REQUIRED
                );

                $this->logger->info('disabling cn', array('cn' => $commonName));

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->staticConfig->disableCommonName($commonName),
                    )
                );

                return $response;
            }
        );

        $service->delete(
            '/ccd/disable',
            function (Request $request) {
                $commonName = InputValidation::commonName(
                    $request->getUrl()->getQueryParameter('common_name'),
                    true // REQUIRED
                );

                $this->logger->info('enabling cn', array('cn' => $commonName));

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->staticConfig->enableCommonName($commonName),
                    )
                );

                return $response;
            }
        );

        $service->get(
            '/ccd/disable',
            function (Request $request) {
                // we typically deal with CNs, not user IDs, but the part of 
                // the CN before the first '_' is considered the user ID
                $userId = InputValidation::userId(
                    $request->getUrl()->getQueryParameter('user_id'),
                    false // OPTIONAL
                );

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => true,
                        'disabled' => $this->staticConfig->getDisabledCommonNames($userId),
                    )
                );

                return $response;
            }
        );
    }
}
