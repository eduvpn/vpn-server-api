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
use fkooman\IO\IO;

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

    /** @var ConnectionLog */
    private $connectionLog;

    /** @var \Monolog\Logger */
    private $logger;

    /** @var \fkooman\IO\IO */
    private $io;

    public function __construct(ServerManager $serverManager, CcdHandler $ccdHandler, CrlFetcher $crlFetcher, ConnectionLog $connectionLog = null, Logger $logger = null, IO $io = null)
    {
        $this->serverManager = $serverManager;
        $this->ccdHandler = $ccdHandler;
        $this->crlFetcher = $crlFetcher;
        $this->connectionLog = $connectionLog;
        $this->logger = $logger;

        if (is_null($io)) {
            $io = new IO();
        }
        $this->io = $io;

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

        $this->get(
            '/ccd/static',
           function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getUrl()->getQueryParameter('common_name');
                if (is_null($commonName)) {
                    throw new BadRequestException('missing common_name');
                }
                Utils::validateCommonName($commonName);

                // XXX we have to remove the netmask again here, ugly!
                $static = $this->ccdHandler->getStaticAddresses($commonName);
                if (!is_null($static['v4'])) {
                    $static['v4'] = explode(' ', $static['v4'])[0];
                }
                if (!is_null($static['v6'])) {
                    $static['v6'] = explode('/', $static['v6'])[0];
                }

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => true,
                        'static' => $static,
                    )
                );

                return $response;
            }
        );

        $this->post(
            '/ccd/static',
           function (Request $request, UserInfoInterface $userInfo) {
                $commonName = $request->getPostParameter('common_name');
                if (is_null($commonName)) {
                    throw new BadRequestException('missing common_name');
                }
                Utils::validateCommonName($commonName);

                $v4 = $request->getPostParameter('v4');
                if (!is_null($v4)) {
                    Utils::validateV4Address($v4);
                    // XXX do something about hardcoded netmask!
                    $v4 = sprintf('%s 255.255.255.0', $v4);
                }

                $v6 = $request->getPostParameter('v6');
                if (!is_null($v6)) {
                    Utils::validateV6Address($v6);
                    // XXX do we also need to specify the router for v6?
                    $v6 = sprintf('%s/64', $v6);
                }

                $this->logInfo('setting static IP', array('api_user' => $userInfo->getUserId(), 'cn' => $commonName, 'v4' => $v4, 'v6' => $v6));

                $response = new JsonResponse();
                $response->setBody(
                    array(
                        'ok' => $this->ccdHandler->setStaticAddresses($commonName, $v4, $v6),
                    )
                );

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
            function (Request $request) {
                $showDate = $request->getUrl()->getQueryParameter('showDate');
                if (is_null($showDate)) {
                    $showDate = date('Y-m-d', $this->io->getTime());
                }
                if (!is_string($showDate)) {
                    $showDate = date('Y-m-d', $this->io->getTime());
                }
                Utils::validateDate($showDate);
                $showDateUnix = strtotime($showDate);

                $minDate = strtotime('today -31 days');
                $maxDate = strtotime('tomorrow');

                if ($showDateUnix < $minDate || $showDateUnix >= $maxDate) {
                    throw new BadRequestException('invalid date range');
                }

                $showDateUnixMin = strtotime('today', $showDateUnix);
                $showDateUnixMax = strtotime('tomorrow', $showDateUnix);

                $response = new JsonResponse();
                if (is_null($this->connectionLog)) {
                    $responseData = array(
                        'ok' => false,
                        'error' => 'unable to connect to log database',
                    );
                } else {
                    $responseData = array(
                        'ok' => true,
                        'history' => $this->connectionLog->getConnectionHistory($showDateUnixMin, $showDateUnixMax),
                    );
                }
                $response->setBody($responseData);

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
