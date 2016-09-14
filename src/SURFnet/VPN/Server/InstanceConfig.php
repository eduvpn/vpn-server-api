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

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Server\Exception\InstanceException;

/**
 * Read the configuration of a particular instance.
 */
class InstanceConfig extends Config
{
    /**
     * Retrieve a configuration object for a pool.
     */
    public function pool($poolId)
    {
        if (!in_array($poolId, $this->pools())) {
            throw new InstanceException(sprintf('pool "%s" not found', $poolId));
        }

        return new PoolConfig($this->configData['vpnPools'][$poolId]);
    }

    /**
     * Retrieve a list of all pools.
     */
    public function pools()
    {
        return array_keys($this->v('vpnPools', []));
    }

    public function groupProviders()
    {
        return array_keys($this->v('groupProviders', []));
    }

    public function groupProvider($groupProviderId)
    {
        if (!in_array($groupProviderId, $this->groupProviders())) {
            throw new InstanceException(sprintf('group provider "%s" not found', $groupProviderId));
        }

        return $this->configData['groupProviders'][$groupProviderId];
    }

    public function apiConsumers()
    {
        return $this->v('apiConsumers');
    }

    public function instanceNumber()
    {
        return $this->v('instanceNumber');
    }
}