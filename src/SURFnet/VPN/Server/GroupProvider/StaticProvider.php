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

class StaticProvider implements GroupProviderInterface
{
    /** @var array */
    private $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public function getGroups($userId)
    {
        if (!is_array($this->configData)) {
            return [];
        }

        $memberOf = [];
        foreach ($this->configData as $groupId => $groupEntry) {
            if (!is_array($groupEntry)) {
                continue;
            }
            $displayName = $groupId;

            if (!array_key_exists('members', $groupEntry)) {
                continue;
            }

            if (!in_array($userId, $groupEntry['members'])) {
                continue;
            }

            if (array_key_exists('displayName', $groupEntry)) {
                $displayName = $groupEntry['displayName'];
            }

            $memberOf[] = [
                'id' => $groupId,
                'displayName' => $displayName,
            ];
        }

        return $memberOf;
    }
}
