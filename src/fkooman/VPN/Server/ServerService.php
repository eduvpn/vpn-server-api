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

    public function __construct(ServerManager $serverManager, CcdHandler $ccdHandler, CrlFetcher $crlFetcher)
    {
        $this->serverManager = $serverManager;
        $this->ccdHandler = $ccdHandler;
        $this->crlFetcher = $crlFetcher;

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
            function (Request $request) {
                $commonName = $request->getPostParameter('common_name');
                Utils::validateCommonName($commonName);

                $response = new JsonResponse();
                $response->setBody($this->serverManager->kill($commonName));

                return $response;
            }
        );

        $this->post(
            '/ccd/disable',
            function (Request $request) {
                $commonName = $request->getPostParameter('common_name');
                Utils::validateCommonName($commonName);

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->ccdHandler->disableCommonName($commonName),
                    )
                );

                return $response;
            }
        );

        $this->post(
            '/ccd/enable',
            function (Request $request) {
                $commonName = $request->getPostParameter('common_name');
                Utils::validateCommonName($commonName);

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
            function (Request $request) {
                // we typically deal with CNs, not user IDs, but the part of 
                // the CN before the first '_' is considered the user ID
                $userId = $request->getUrl()->getQueryParameter('user_id');
                if (!is_null($userId)) {
                    Utils::validateUserId($userId);
                }

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'items' => $this->ccdHandler->getDisabledCommonNames($userId),
                    )
                );

                return $response;
            }
        );

        $this->post(
            '/crl/fetch',
            function (Request $request) {
                $response = new JsonResponse();
                $response->setBody($this->crlFetcher->fetch());

                return $response;
            }
        );
    }
}
