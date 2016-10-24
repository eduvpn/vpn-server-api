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

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\ProfileConfig;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Request;

class ConnectionsModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var Users */
    private $users;

    /** @var CommonNames */
    private $commonNames;

    /** @var ConnectionLog */
    private $connectionLog;

    /** @var array */
    private $groupProviders;

    public function __construct(Config $config, Users $users, CommonNames $commonNames, ConnectionLog $connectionLog, array $groupProviders)
    {
        $this->config = $config;
        $this->users = $users;
        $this->commonNames = $commonNames;
        $this->connectionLog = $connectionLog;
        $this->groupProviders = $groupProviders;
    }

    public function init(Service $service)
    {
        $service->post(
            '/connect',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-server-node']);

                $profileId = $request->getPostParameter('profile_id');
                InputValidation::profileId($profileId);
                $commonName = $request->getPostParameter('common_name');
                InputValidation::commonName($commonName);
                $ip = $request->getPostParameter('ip');
                InputValidation::ip4($ip);
                $ip6 = $request->getPostParameter('ip6');
                InputValidation::ip6($ip6);
                $connectedAt = $request->getPostParameter('connected_at');
                InputValidation::connectedAt($connectedAt);

                $userId = self::getUserId($commonName);

                // check if user is disabled
                if (true === $this->users->isDisabled($userId)) {
                    return false;
                }

                // check if the common_name is disabled
                if (true === $this->commonNames->isDisabled($commonName)) {
                    return false;
                }

                // if the ACL is enabled, verify that the user is allowed to
                // connect
                $profileConfig = new ProfileConfig($this->config->v('vpnProfiles', $profileId));
                if ($profileConfig->v('enableAcl')) {
                    $userGroups = [];
                    foreach ($this->groupProviders as $groupProvider) {
                        $userGroups = array_merge($userGroups, $groupProvider->getGroups($userId));
                    }

                    if (false === self::isMember($userGroups, $profileConfig->v('aclGroupList'))) {
                        return false;
                    }
                }

                $responseData = $this->connectionLog->connect($profileId, $commonName, $ip, $ip6, $connectedAt);

                return new ApiResponse('connect', $responseData);
            }
        );

        $service->post(
            '/disconnect',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-server-node']);

                $profileId = $request->getPostParameter('profile_id');
                InputValidation::profileId($profileId);
                $commonName = $request->getPostParameter('common_name');
                InputValidation::commonName($commonName);
                $ip = $request->getPostParameter('ip');
                InputValidation::ip4($ip);
                $ip6 = $request->getPostParameter('ip6');
                InputValidation::ip6($ip6);
                $connectedAt = $request->getPostParameter('connected_at');
                InputValidation::connectedAt($connectedAt);
                $disconnectedAt = $request->getPostParameter('disconnected_at');
                InputValidation::disconnectedAt($disconnectedAt);
                $bytesTransferred = $request->getPostParameter('bytes_transferred');
                InputValidation::bytesTransferred($bytesTransferred);

                $responseData = $this->connectionLog->disconnect($profileId, $commonName, $ip, $ip6, $connectedAt, $disconnectedAt, $bytesTransferred);

                return new ApiResponse('disconnect', $responseData);
            }
        );
    }

    private static function getUserId($commonName)
    {
        // XXX do not repeat this everywhere

        // return the part before the first underscore, it is already validated
        // so we can be sure this is fine
        return substr($commonName, 0, strpos($commonName, '_'));
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
