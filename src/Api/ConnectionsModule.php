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

use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\ProfileConfig;

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
        $profileId = $request->getPostParameter('profile_id');
        InputValidation::profileId($profileId);
        $commonName = $request->getPostParameter('common_name');
        InputValidation::commonName($commonName);
        $ip4 = $request->getPostParameter('ip4');
        InputValidation::ip4($ip4);
        $ip6 = $request->getPostParameter('ip6');
        InputValidation::ip6($ip6);

        // normalize the IPv6 address
        $ip6 = inet_ntop(inet_pton($ip6));

        $connectedAt = $request->getPostParameter('connected_at');
        InputValidation::connectedAt($connectedAt);

        if (true !== $response = $this->verifyConnection($profileId, $commonName)) {
            return $response;
        }

        if (false == $this->storage->clientConnect($profileId, $commonName, $ip4, $ip6, $connectedAt)) {
            return new ApiResponse('connect', ['ok' => false, 'error' => 'unable to write connect event to log']);
        }

        return new ApiResponse('connect', ['ok' => true]);
    }

    private function verifyConnection($profileId, $commonName)
    {
        // verify status of certificate/user
        if (false === $result = $this->storage->getUserCertificateStatus($commonName)) {
            return new ApiResponse('connect', ['ok' => false, 'error' => 'user or certificate does not exist']);
        }

        if ($result['user_is_disabled']) {
            return new ApiResponse('connect', ['ok' => false, 'error' => 'user is disabled']);
        }

        if ($result['certificate_is_disabled']) {
            return new ApiResponse('connect', ['ok' => false, 'error' => 'certificate is disabled']);
        }

        return $this->verifyAcl($profileId, $result['external_user_id']);
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
                return new ApiResponse('connect', ['ok' => false, 'error' => 'user not in ACL']);
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

    public function disconnect(Request $request)
    {
        $profileId = $request->getPostParameter('profile_id');
        InputValidation::profileId($profileId);
        $commonName = $request->getPostParameter('common_name');
        InputValidation::commonName($commonName);
        $ip4 = $request->getPostParameter('ip4');
        InputValidation::ip4($ip4);
        $ip6 = $request->getPostParameter('ip6');
        InputValidation::ip6($ip6);

        // normalize the IPv6 address
        $ip6 = inet_ntop(inet_pton($ip6));

        $connectedAt = $request->getPostParameter('connected_at');
        InputValidation::connectedAt($connectedAt);

        $disconnectedAt = $request->getPostParameter('disconnected_at');
        InputValidation::disconnectedAt($disconnectedAt);

        $bytesTransferred = $request->getPostParameter('bytes_transferred');
        InputValidation::bytesTransferred($bytesTransferred);

        if (false === $this->storage->clientDisconnect($profileId, $commonName, $ip4, $ip6, $connectedAt, $disconnectedAt, $bytesTransferred)) {
            return new ApiResponse('disconnect', ['ok' => false, 'error' => 'unable to write disconnect event to log']);
        }

        return new ApiResponse('disconnect', ['ok' => true]);
    }

    public function verifyOtp(Request $request)
    {
    }
}
