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
use SURFnet\VPN\Common\Config;

class StaticProvider implements GroupProviderInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var string */
    private $dataDir;

    public function __construct(Config $config, $dataDir)
    {
        $this->config = $config;
        $this->dataDir = $dataDir;
    }

    /**
     * Get the groups a user is a member of.
     *
     * @param string userId the userID of the user to request the groups of
     *
     * @return array the groups as an array containing the keys "id" and
     *               "displayName", empty array if no groups are available for this user
     */
    public function getGroups($userId)
    {
        $memberOf = [];

        $groupIdList = array_keys($this->config->v());
        foreach ($groupIdList as $groupId) {
            $memberList = $this->config->v($groupId, 'members');
            if (!is_array($memberList) || !in_array($userId, $memberList)) {
                continue;
            }

            $memberOf[] = [
                'id' => $groupId,
                'displayName' => $this->config->v($groupId, 'displayName'),
            ];
        }

        return $memberOf;
    }
}
