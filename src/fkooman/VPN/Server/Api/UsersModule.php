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
use fkooman\VPN\Server\AclInterface;
use fkooman\VPN\Server\Disable;
use fkooman\VPN\Server\OtpSecret;
use fkooman\VPN\Server\VootToken;
use fkooman\VPN\Server\ApiResponse;

class UsersModule implements ServiceModuleInterface
{
    /** @var \fkooman\VPN\Server\Disable */
    private $users;

    /** @var \fkooman\VPN\Server\OtpSecret */
    private $otpSecret;

    /** @var \fkooman\VPN\Server\VootToken */
    private $vootToken;

    /** @var \fkooman\VPN\Server\AclInterface */
    private $acl;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(Disable $users, OtpSecret $otpSecret, VootToken $vootToken, AclInterface $acl, LoggerInterface $logger)
    {
        $this->users = $users;
        $this->otpSecret = $otpSecret;
        $this->vootToken = $vootToken;
        $this->acl = $acl;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        //
        // DISABLED
        //
        $service->get(
            '/users/disabled',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);

                return new ApiResponse('users', $this->users->getDisabled());
            }
        );

        $service->get(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::userId($userId);

                return new ApiResponse('disabled', $this->users->getDisable($userId));
            }
        );

        $service->post(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);
                $this->logger->info(sprintf('disabling user "%s"', $userId));

                return new ApiResponse('ok', $this->users->setDisable($userId, true));
            }
        );

        $service->delete(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);
                $this->logger->info(sprintf('enabling user "%s"', $userId));

                return new ApiResponse('ok', $this->users->setDisable($userId, false));
            }
        );

        //
        // OTP_SECRETS
        //
        $service->get(
            '/users/otp_secrets',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);

                return new ApiResponse('users', $this->otpSecret->getOtpSecrets());
            }
        );

        $service->get(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::userId($userId);

                $hasOtpSecret = false !== $this->otpSecret->getOtpSecret($userId);

                return new ApiResponse('otp_secret', $hasOtpSecret);
            }
        );

        $service->post(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['portal']);
                InputValidation::userId($userId);
                $otpSecret = $request->getPostParameter('otp_secret');
                InputValidation::otpSecret($otpSecret);

                return new ApiResponse('ok', $this->otpSecret->setOtpSecret($userId, $otpSecret));
            }
        );

        $service->delete(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);

                return new ApiResponse('ok', $this->otpSecret->setOtpSecret($userId, false));
            }
        );

        //
        // GROUPS
        //
        $service->get(
            '/users/groups/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::userId($userId);

                return new ApiResponse('groups', $this->acl->getGroups($userId));
            }
        );

        //
        // VOOT_TOKENS
        //
        $service->get(
            '/users/voot_tokens/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['portal']);
                InputValidation::userId($userId);

                $hasVootToken = false !== $this->vootToken->getVootToken($userId);

                return new ApiResponse('voot_token', $hasVootToken);
            }
        );

        $service->post(
            '/users/voot_tokens/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['portal']);
                InputValidation::userId($userId);
                $vootToken = $request->getPostParameter('voot_token');
                InputValidation::vootToken($vootToken);

                return new ApiResponse('ok', $this->vootToken->setVootToken($userId, $vootToken));
            }
        );
    }
}
