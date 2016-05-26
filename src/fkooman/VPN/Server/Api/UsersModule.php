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
use fkooman\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use fkooman\VPN\Server\InputValidation;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\VPN\Server\Disable;
use fkooman\VPN\Server\OtpSecret;

// XXX think about otp_secrets vs otp_secret, and disable vs disabled

class UsersModule implements ServiceModuleInterface
{
    /** @var \fkooman\VPN\Server\Disable */
    private $users;

    /** @var \fkooman\VPN\Server\OtpSecret */
    private $otpSecret;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(Disable $users, OtpSecret $otpSecret, LoggerInterface $logger)
    {
        $this->users = $users;
        $this->otpSecret = $otpSecret;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        $service->get(
            '/users/disabled',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);

                return self::getResponse('users', $this->users->getDisabled());
            }
        );

        $service->get(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::userId($userId);

                return self::getResponse('disabled', $this->users->getDisable($userId));
            }
        );

        $service->post(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);
                $this->logger->info(sprintf('disabling user "%s"', $userId));

                return self::getResponse('ok', $this->users->setDisable($userId, true));
            }
        );

        $service->delete(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);
                $this->logger->info(sprintf('enabling user "%s"', $userId));

                return self::getResponse('ok', $this->users->setDisable($userId, false));
            }
        );

        $service->get(
            '/users/otp_secrets',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);

                return self::getResponse('otp_secrets', $this->otpSecret->getOtpSecrets());
            }
        );

        $service->get(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::userId($userId);

                $hasOtpSecret = false !== $this->otpSecret->getOtpSecret($userId);

                return self::getResponse('otp_secret', $hasOtpSecret);
            }
        );

        $service->post(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['portal']);
                InputValidation::userId($userId);
                $otpSecret = $request->getPostParameter('otp_secret');
                InputValidation::otpSecret($otpSecret);

                return self::getResponse('ok', $this->otpSecret->setOtpSecret($userId, $otpSecret));
            }
        );

        $service->delete(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);

                return self::getResponse('ok', $this->otpSecret->setOtpSecret($userId, false));
            }
        );
    }

    private static function getResponse($key, $responseData)
    {
        $response = new JsonResponse();
        $response->setBody(
            [
                'data' => [
                    $key => $responseData,
                ],
            ]
        );

        return $response;
    }
}
