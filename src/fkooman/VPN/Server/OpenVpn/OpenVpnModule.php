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

namespace fkooman\VPN\Server\OpenVpn;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use fkooman\VPN\Server\InputValidation;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class OpenVpnModule implements ServiceModuleInterface
{
    /** @var ServerManager */
    private $serverManager;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(ServerManager $serverManager, LoggerInterface $logger)
    {
        $this->serverManager = $serverManager;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        $service->get(
            '/openvpn/status',
            function (Request $request, TokenInfo $tokenInfo) {
                self::requireScope($tokenInfo, ['admin']);

                $response = new JsonResponse();
                $response->setBody($this->serverManager->status());

                return $response;
            }
        );

        $service->post(
            '/openvpn/kill',
            function (Request $request, TokenInfo $tokenInfo) {
                self::requireScope($tokenInfo, ['admin', 'portal']);

                $commonName = $request->getPostParameter('common_name');
                InputValidation::commonName($commonName);

                $this->logger->info('killing cn', array('cn' => $commonName));

                $response = new JsonResponse();
                $response->setBody($this->serverManager->kill($commonName));

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
