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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use SURFnet\VPN\Server\GroupProviderInterface;
use SURFnet\VPN\Server\InstanceConfig;

class VootProvider implements GroupProviderInterface
{
    /** @var string */
    private $dataDir;

    /** @var \SURFnet\VPN\Server\InstanceConfig */
    private $instanceConfig;

    public function __construct($dataDir, InstanceConfig $config)
    {
        $this->dataDir = $dataDir;
        $this->instanceConfig = $instanceConfig;
    }

    public function getGroups($userId)
    {
        if (false === $bearerToken = @file_get_contents(sprintf('%s/users/voot_tokens/%s', $dataDir, $userId))) {
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
                $this->instanceConfig->v('GroupProviders', 'VootProvider', 'apiUrl'),
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
            $displayName = $groupEntry['id'];

            // override displayName if one is set
            if (array_key_exists('displayName', $groupEntry)) {
                // check if it is multilanguage
                if (is_string($groupEntry['displayName'])) {
                    $displayName = $groupEntry['displayName'];
                } else {
                    // take english if available, otherwise first
                    if (array_key_exists('en', $groupEntry['displayName'])) {
                        $displayName = $groupEntry['displayName']['en'];
                    } else {
                        $displayName = array_values($groupEntry['displayName'])[0];
                    }
                }
            }

            $memberOf[] = [
                'id' => $groupEntry['id'],
                'displayName' => $displayName,
            ];
        }

        return $memberOf;
    }
}
