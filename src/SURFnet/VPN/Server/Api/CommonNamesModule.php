<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Server\Api;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use Psr\Log\LoggerInterface;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class CommonNamesModule implements ServiceModuleInterface
{
    /** @var CommonNames */
    private $commonNames;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(CommonNames $commonNames, LoggerInterface $logger)
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

        $service->post(
            '/common_names/disabled/:commonName',
            function ($commonName, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::commonName($commonName);
                $this->logger->info(sprintf('disabling common_name "%s"', $commonName));

                return new ApiResponse('ok', $this->commonNames->setDisabled($commonName));
            }
        );

        $service->delete(
            '/common_names/disabled/:commonName',
            function ($commonName, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::commonName($commonName);
                $this->logger->info(sprintf('enabling common_name "%s"', $commonName));

                return new ApiResponse('ok', $this->commonNames->setEnabled($commonName));
            }
        );
    }
}
