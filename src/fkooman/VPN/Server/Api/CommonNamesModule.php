<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

namespace fkooman\VPN\Server\Api;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use Psr\Log\LoggerInterface;
use fkooman\VPN\Server\InputValidation;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\VPN\Server\Disable;
use fkooman\VPN\Server\ApiResponse;

class CommonNamesModule implements ServiceModuleInterface
{
    /** @var Disable */
    private $commonNames;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(Disable $commonNames, LoggerInterface $logger)
    {
        $this->commonNames = $commonNames;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        $service->get(
            '/common_names/disabled',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);

                return new ApiResponse('common_names', $this->commonNames->getDisabled());
            }
        );

        $service->get(
            '/common_names/disabled/:commonName',
            function ($commonName, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::commonName($commonName);

                return new ApiResponse('disabled', $this->commonNames->getDisable($commonName));
            }
        );

        $service->post(
            '/common_names/disabled/:commonName',
            function ($commonName, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::commonName($commonName);
                $this->logger->info(sprintf('disabling common_name "%s"', $commonName));

                return new ApiResponse('ok', $this->commonNames->setDisable($commonName, true));

            }
        );

        $service->delete(
            '/common_names/disabled/:commonName',
            function ($commonName, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::commonName($commonName);
                $this->logger->info(sprintf('enabling common_name "%s"', $commonName));

                return new ApiResponse('ok', $this->commonNames->setDisable($commonName, false));
            }
        );
    }
}
