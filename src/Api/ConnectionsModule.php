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

use Base32\Base32;
use Otp\Otp;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\ApiErrorResponse;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\ProfileConfig;
use SURFnet\VPN\Server\Storage;

class ConnectionsModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    /** @var array */
    private $groupProviders;

    public function __construct(Config $config, Storage $storage, array $groupProviders)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->groupProviders = $groupProviders;
    }

    public function init(Service $service)
    {
        $service->post(
            '/connect',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->connect($request);
            }
        );

        $service->post(
            '/disconnect',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->disconnect($request);
            }
        );

        $service->post(
            '/verify_otp',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return $this->verifyOtp($request);
            }
        );
    }

    public function connect(Request $request)
    {
        $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->getPostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->getPostParameter('ip6'));
        $connectedAt = InputValidation::connectedAt($request->getPostParameter('connected_at'));

        if (true !== $response = $this->verifyConnection($profileId, $commonName)) {
            return $response;
        }

        if (false == $this->storage->clientConnect($profileId, $commonName, $ip4, $ip6, $connectedAt)) {
            return new ApiErrorResponse('connect', 'unable to write connect event to log');
        }

        return new ApiResponse('connect');
    }

    public function disconnect(Request $request)
    {
        $profileId = InputValidation::profileId($request->getPostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->getPostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->getPostParameter('ip6'));

        $connectedAt = InputValidation::connectedAt($request->getPostParameter('connected_at'));
        $disconnectedAt = InputValidation::disconnectedAt($request->getPostParameter('disconnected_at'));
        $bytesTransferred = InputValidation::bytesTransferred($request->getPostParameter('bytes_transferred'));

        if (false === $this->storage->clientDisconnect($profileId, $commonName, $ip4, $ip6, $connectedAt, $disconnectedAt, $bytesTransferred)) {
            return new ApiErrorResponse('disconnect', 'unable to write disconnect event to log');
        }

        return new ApiResponse('disconnect');
    }

    public function verifyOtp(Request $request)
    {
        $commonName = InputValidation::commonName($request->getPostParameter('common_name'));
        // we do not need 'otp_type', as only 'totp' is supported at the moment
        InputValidation::otpType($request->getPostParameter('otp_type'));
        $totpKey = InputValidation::totpKey($request->getPostParameter('totp_key'));

        $certInfo = $this->storage->getUserCertificateInfo($commonName);
        $userId = $certInfo['user_id'];

        if (!$this->storage->hasTotpSecret($userId)) {
            return new ApiErrorResponse('verify_otp', 'user has no OTP secret');
        }

        $totpSecret = $this->storage->getTotpSecret($userId);

        $otp = new Otp();
        if (!$otp->checkTotp(Base32::decode($totpSecret), $totpKey)) {
            return new ApiErrorResponse('verify_otp', 'invalid OTP key');
        }

        if (false === $this->storage->recordTotpKey($userId, $totpKey, time())) {
            return new ApiErrorResponse('verify_otp', 'OTP key replay');
        }

        return new ApiResponse('verify_otp');
    }

    private function verifyConnection($profileId, $commonName)
    {
        // verify status of certificate/user
        if (false === $result = $this->storage->getUserCertificateInfo($commonName)) {
            return new ApiErrorResponse('connect', 'user or certificate does not exist');
        }

        if ($result['user_is_disabled']) {
            return new ApiErrorResponse('connect', 'user is disabled');
        }

        if ($result['certificate_is_disabled']) {
            return new ApiErrorResponse('connect', 'certificate is disabled');
        }

        return $this->verifyAcl($profileId, $result['user_id']);
    }

    private function verifyAcl($profileId, $externalUserId)
    {
        // verify ACL
        $profileConfig = new ProfileConfig($this->config->v('vpnProfiles', $profileId));
        if ($profileConfig->v('enableAcl')) {
            // ACL enabled
            $userGroups = [];
            foreach ($this->groupProviders as $groupProvider) {
                $userGroups = array_merge($userGroups, $groupProvider->getGroups($externalUserId));
            }

            if (false === self::isMember($userGroups, $profileConfig->v('aclGroupList'))) {
                return new ApiErrorResponse('connect', 'user not in ACL');
            }
        }

        return true;
    }

    private static function isMember(array $memberOf, array $aclGroupList)
    {
        // one of the groups must be listed in the profile ACL list
        foreach ($memberOf as $memberGroup) {
            if (in_array($memberGroup['id'], $aclGroupList)) {
                return true;
            }
        }

        return false;
    }
}
