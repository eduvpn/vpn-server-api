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

namespace SURFnet\VPN\Server\Acl\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use SURFnet\VPN\Server\Acl\ProviderInterface;
use SURFnet\VPN\Common\Config;

class VootProvider implements ProviderInterface
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
        if (false === $bearerToken = @file_get_contents(sprintf('%s/users/voot_tokens/%s', $this->dataDir, $userId))) {
            return [];
        }

        // fetch the groups and extract the membership data
        return self::extractMembership(
            $this->fetchGroups($bearerToken)
        );
    }

    private function fetchGroups($bearerToken)
    {
        $httpClient = new Client();
        try {
            return $httpClient->get(
                $this->config->v('apiUrl'),
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $bearerToken),
                    ],
                ]
            )->json();
        } catch (TransferException $e) {
            return [];
        }
    }

    private static function extractMembership(array $responseData)
    {
        $memberOf = [];
        foreach ($responseData as $groupEntry) {
            if (!is_array($groupEntry)) {
                continue;
            }
            if (!array_key_exists('id', $groupEntry)) {
                continue;
            }
            if (!is_string($groupEntry['id'])) {
                continue;
            }
            $displayName = self::getDisplayName($groupEntry);

            $memberOf[] = [
                'id' => $groupEntry['id'],
                'displayName' => $displayName,
            ];
        }

        return $memberOf;
    }

    private static function getDisplayName(array $groupEntry)
    {
        if (!array_key_exists('displayName', $groupEntry)) {
            return $groupEntry['id'];
        }

        if (is_string($groupEntry['displayName'])) {
            return $groupEntry['displayName'];
        }

        if (is_array($groupEntry['displayName'])) {
            if (array_key_exists('en', $groupEntry['displayName'])) {
                return $groupEntry['displayName']['en'];
            }

            return array_values($groupEntry['displayName'])[0];
        }

        return $groupEntry['id'];
    }
}
