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
namespace fkooman\VPN\Server;

use fkooman\Rest\Service;
use fkooman\Http\JsonResponse;
use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;
use Monolog\Logger;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;

/**
 * This class registers and handles routes.
 */
class ServerService extends Service
{
    /** @var ServerManager */
    private $serverManager;

    /** @var CcdHandler */
    private $ccdHandler;

    /** @var CrlFetcher */
    private $crlFetcher;

    /** @var ClientConnection */
    private $clientConnection;

    /** @var \Monolog\Logger */
    private $logger;

    public function __construct(ServerManager $serverManager, CcdHandler $ccdHandler, CrlFetcher $crlFetcher, ClientConnection $clientConnection, Logger $logger = null)
    {
        $this->serverManager = $serverManager;
        $this->ccdHandler = $ccdHandler;
        $this->crlFetcher = $crlFetcher;
        $this->clientConnection = $clientConnection;
        $this->logger = $logger;

        parent::__construct();
        $this->registerRoutes();
    }

    private function registerRoutes()
    {
        $this->get(
            '/status',
            function (Request $request) {
                $response = new JsonResponse();
                $response->setBody($this->serverManager->status());

                return $response;
            }
        );

        $this->get(
            '/load-stats',
            function (Request $request) {
                $response = new JsonResponse();
                $response->setBody($this->serverManager->loadStats());

                return $response;
            }
        );

        $this->get(
            '/version',
            function (Request $request) {
                $response = new JsonResponse();
                $response->setBody($this->serverManager->version());

                return $response;
            }
        );

        $this->post(
            '/kill',
            function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getPostParameter('common_name');
                Utils::validateCommonName($commonName);

                $this->logInfo('killing cn', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName));

                $response = new JsonResponse();
                $response->setBody($this->serverManager->kill($commonName));

                return $response;
            }
        );

        $this->post(
            '/ccd/disable',
            function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getPostParameter('common_name');
                if (is_null($commonName)) {
                    throw new BadRequestException('missing common_name');
                }
                Utils::validateCommonName($commonName);

                $this->logInfo('disabling cn', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName));

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->ccdHandler->disableCommonName($commonName),
                    )
                );

                return $response;
            }
        );

        $this->delete(
            '/ccd/disable',
            function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getUrl()->getQueryParameter('common_name');
                Utils::validateCommonName($commonName);

                $this->logInfo('enabling cn', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName));

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->ccdHandler->enableCommonName($commonName),
                    )
                );

                return $response;
            }
        );

        $this->get(
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
                        'disabled' => $this->ccdHandler->getDisabledCommonNames($userId),
                    )
                );

                return $response;
            }
        );

        $this->post(
            '/crl/fetch',
            function (Request $request, UserInfoInterface $userInfo) {

                $this->logInfo('fetching CRL', array('api_user' => $userInfo->getUserId()));

                $response = new JsonResponse();
                $response->setBody($this->crlFetcher->fetch());

                return $response;
            }
        );

        $this->get(
            '/log/history',
            function (Request $request, UserInfoInterface $userInfo) {
                $response = new JsonResponse();
                $response->setBody($this->clientConnection->getConnectionHistory());

                return $response;
            }
        );
    }

    private function logInfo($m, array $context)
    {
        if (!is_null($this->logger)) {
            $this->logger->addInfo($m, $context);
        }
    }
}
