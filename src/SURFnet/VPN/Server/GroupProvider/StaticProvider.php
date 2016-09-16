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
namespace SURFnet\VPN\Server\GroupProvider;

use SURFnet\VPN\Server\GroupProviderInterface;
use SURFnet\VPN\Server\InstanceConfig;

class StaticProvider implements GroupProviderInterface
{
    /** @var string */
    private $dataDir;

    /** @var \SURFnet\VPN\Server\InstanceConfig */
    private $instanceConfig;

    public function __construct($dataDir, InstanceConfig $instanceConfig)
    {
        $this->dataDir = $dataDir;
        $this->instanceConfig = $instanceConfig;
    }

    public function getGroups($userId)
    {
        $memberOf = [];

        $groupIdList = array_keys($this->instanceConfig->v('groupProviders', 'StaticProvider'));
        foreach ($groupIdList as $groupId) {
            $memberList = $this->instanceConfig->v('groupProviders', 'StaticProvider', $groupId, 'members');
            if (!is_array($memberList) || !in_array($userId, $memberList)) {
                continue;
            }

            $memberOf[] = [
                'id' => $groupId,
                'displayName' => $this->instanceConfig->v('groupProviders', 'StaticProvider', $groupId, 'displayName'),
            ];
        }

        return $memberOf;
    }
}
