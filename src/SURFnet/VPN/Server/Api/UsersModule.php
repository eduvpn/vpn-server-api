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

/**
 * Handle API calls for Users.
 *
 * XXX more logging!
 */
class UsersModule implements ServiceModuleInterface
{
    /** @var Users */
    private $users;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(Users $users, LoggerInterface $logger)
    {
        $this->users = $users;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        // DISABLED
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
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);

                return new ApiResponse('disabled', $this->users->isDisabled($userId));
            }
        );

        $service->post(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);
                $this->logger->info(sprintf('disabling user "%s"', $userId));

                return new ApiResponse('ok', $this->users->setDisabled($userId));
            }
        );

        $service->delete(
            '/users/disabled/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);
                $this->logger->info(sprintf('enabling user "%s"', $userId));

                return new ApiResponse('ok', $this->users->setEnabled($userId));
            }
        );

        // OTP_SECRETS
        $service->get(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
                InputValidation::userId($userId);

                return new ApiResponse('otp_secret', $this->users->hasOtpSecret($userId));
            }
        );

        $service->post(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['portal']);
                InputValidation::userId($userId);
                $otpSecret = $request->getPostParameter('otp_secret');
                InputValidation::otpSecret($otpSecret);

                return new ApiResponse('ok', $this->users->setOtpSecret($userId, $otpSecret));
            }
        );

        $service->delete(
            '/users/otp_secrets/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);
                InputValidation::userId($userId);

                return new ApiResponse('ok', $this->users->deleteOtpSecret($userId));
            }
        );

        // GROUPS
//        $service->get(
//            '/users/groups/:userId',
//            function ($userId, Request $request, TokenInfo $tokenInfo) {
//                $tokenInfo->getScope()->requireScope(['admin', 'portal']);
//                InputValidation::userId($userId);

//                return new ApiResponse('groups', $this->acl->getGroups($userId));
//            }
//        );

        // VOOT_TOKENS
        $service->get(
            '/users/voot_tokens/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['portal']);
                InputValidation::userId($userId);

                return new ApiResponse('voot_token', $this->users->hasVootToken($userId));
            }
        );

        $service->post(
            '/users/voot_tokens/:userId',
            function ($userId, Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['portal']);
                InputValidation::userId($userId);
                $vootToken = $request->getPostParameter('voot_token');
                InputValidation::vootToken($vootToken);

                return new ApiResponse('ok', $this->users->setVootToken($userId, $vootToken));
            }
        );
    }
}
