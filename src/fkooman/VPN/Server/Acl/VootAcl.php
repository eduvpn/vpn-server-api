<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\Server\Acl;

use fkooman\Config\Reader;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use fkooman\VPN\Server\AclInterface;
use fkooman\VPN\Server\VootToken;

class VootAcl implements AclInterface
{
    /** @var \fkooman\Config\Reader */
    private $configReader;

    /** @var \GuzzleHttp\Client */
    private $client;

    public function __construct(Reader $configReader, Client $client = null)
    {
        $this->configReader = $configReader;
        if (is_null($client)) {
            $client = new Client();
        }
        $this->client = $client;
    }

    public function getGroups($userId)
    {
        $tokenDir = $this->configReader->v('VootAcl', 'tokenDir');
        $apiUrl = $this->configReader->v('VootAcl', 'apiUrl');

        $vootToken = new VootToken($tokenDir);
        $bearerToken = $vootToken->getVootToken($userId);

        if (false === $bearerToken) {
            // no Bearer token registered for this user, so assume user is not
            // a member of any groups
            return [];
        }

        // fetch the groups and extract the membership data
        return self::extractMembership(
            $this->fetchGroups($apiUrl, $bearerToken)
        );
    }

    private function fetchGroups($apiUrl, $bearerToken)
    {
        try {
            return $this->client->get(
                $apiUrl,
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
