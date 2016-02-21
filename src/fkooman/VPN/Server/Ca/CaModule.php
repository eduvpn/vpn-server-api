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
namespace fkooman\VPN\Server\Ca;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Http\JsonResponse;

class CaModule implements ServiceModuleInterface
{
    /** @var CrlFetcher */
    private $crlFetcher;

    public function __construct(CrlFetcher $crlFetcher)
    {
        $this->crlFetcher = $crlFetcher;
    }

    public function init(Service $service)
    {
        $service->post(
            '/crl/fetch',
            function (Request $request, UserInfoInterface $userInfo) {

                //$this->logInfo('fetching CRL', array('api_user' => $userInfo->getUserId()));

                $response = new JsonResponse();
                $response->setBody($this->crlFetcher->fetch());

                return $response;
            }
        );
    }
}
