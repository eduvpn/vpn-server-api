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
use fkooman\VPN\Server\Utils;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Http\JsonResponse;
use fkooman\Http\Exception\BadRequestException;

class ConfigModule implements ServiceModuleInterface
{
    /** @var StaticConfig */
    private $staticConfig;

    public function __construct(StaticConfig $staticConfig)
    {
        $this->staticConfig = $staticConfig;
    }

    public function init(Service $service)
    {
        $service->get(
            '/static/ip',
           function (Request $request, UserInfoInterface $userInfo) {
                // we typically deal with CNs, not user IDs, but the part of 
                // the CN before the first '_' is considered the user ID
                $userId = $request->getUrl()->getQueryParameter('user_id');
                $commonName = $request->getUrl()->getQueryParameter('common_name');
                if (!is_null($userId)) {
                    // per user
                    Utils::validateUserId($userId);
                    $ipConfig = $this->staticConfig->getStaticAddresses($userId);
                } elseif (!is_null($commonName)) {
                    // per CN
                    Utils::validateCommonName($commonName);
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
           function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getPostParameter('common_name');
                if (is_null($commonName)) {
                    throw new BadRequestException('missing common_name');
                }
                Utils::validateCommonName($commonName);

                $v4 = $request->getPostParameter('v4');
                if (empty($v4)) {
                    $v4 = null;
                }

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->staticConfig->setStaticAddresses($commonName, $v4),
                    )
                );

                // $this->logInfo('setting static IP', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName, 'v4' => $v4));

                return $response;
            }
        );

        $service->post(
            '/ccd/disable',
            function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getPostParameter('common_name');
                if (is_null($commonName)) {
                    throw new BadRequestException('missing common_name');
                }
                Utils::validateCommonName($commonName);

                // $this->logInfo('disabling cn', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName));

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
            function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getUrl()->getQueryParameter('common_name');
                Utils::validateCommonName($commonName);

                // $this->logInfo('enabling cn', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName));

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
            function (Request $request, UserInfoInterface $userInfo) {
                // we typically deal with CNs, not user IDs, but the part of 
                // the CN before the first '_' is considered the user ID
                $userId = $request->getUrl()->getQueryParameter('user_id');
                if (!is_null($userId)) {
                    Utils::validateUserId($userId);
                }

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
