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
namespace SURFnet\VPN\Server;

use SURFnet\VPN\Server\Exception\ConnectionException;
use RuntimeException;

class Connection
{
    /** @var string */
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function connect(array $envData)
    {
        $userId = self::getUserId($envData['common_name']);

        $dataDir = sprintf('%s/data/%s', $this->baseDir, $envData['INSTANCE_ID']);

        // is the user account disabled?
        // XXX we have to be careful, if the directory is not readable by the
        // openvpn user, it is assumed the user is not disabled! We have to
        // find a more robust solution for this!
        $disabledUsersDir = sprintf('%s/users/disabled', $dataDir);
        if (@file_exists(sprintf('%s/%s', $disabledUsersDir, $userId))) {
            throw new ConnectionException('client not allowed, user is disabled');
        }

        // is the common name disabled?
        // XXX we have to be careful, if the directory is not readable by the
        // openvpn user, it is assumed the user is not disabled! We have to
        // find a more robust solution for this!
        $disabledCommonNamesDir = sprintf('%s/common_names/disabled', $dataDir);
        if (@file_exists(sprintf('%s/%s', $disabledCommonNamesDir, $envData['common_name']))) {
            throw new ConnectionException('client not allowed, CN is disabled');
        }

        $configDir = sprintf('%s/config/%s', $this->baseDir, $envData['INSTANCE_ID']);

        // read the instance/pool configuration
        $instanceConfig = InstanceConfig::fromFile(
            sprintf('%s/config.yaml', $configDir)
        );

        // is the ACL enabled?
        $poolId = $envData['POOL_ID'];
        $poolConfig = new PoolConfig($instanceConfig->v('vpnPools', $poolId));
        if ($poolConfig->v('enableAcl')) {
            $aclGroupProvider = $poolConfig->v('aclGroupProvider');
            $groupProviderClass = sprintf('SURFnet\VPN\Server\GroupProvider\%s', $aclGroupProvider);
            $groupProvider = new $groupProviderClass($dataDir, $instanceConfig);
            $aclGroupList = $poolConfig->v('aclGroupList');

            if (false === self::isMember($groupProvider->getGroups($userId), $aclGroupList)) {
                throw new ConnectionException(sprintf('client not allowed, not a member of "%s"', implode(',', $aclGroupList)));
            }
        }
    }

    private static function getUserId($commonName)
    {
        if (false === $uPos = mb_strpos($commonName, '_')) {
            throw new RuntimeException('unable to extract userId from commonName');
        }

        return mb_substr($commonName, 0, $uPos);
    }

    private static function isMember(array $memberOf, array $aclGroupList)
    {
        // one of the groups must be listed in the pool ACL list
        foreach ($memberOf as $memberGroup) {
            if (in_array($memberGroup['id'], $aclGroupList)) {
                return true;
            }
        }

        return false;
    }
}
