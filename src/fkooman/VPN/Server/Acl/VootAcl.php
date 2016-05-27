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
        $vootToken = new VootToken($this->configReader->v('VootAcl', 'tokenDir'));
        $bearerToken = $vootToken->getVootToken($userId);
        if (false === $bearerToken) {
            return [];
        }

        try {
            $apiUrl = $this->configReader->v('VootAcl', 'apiUrl');

            $responseData = $this->client->get(
                $apiUrl,
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $bearerToken),
                    ],
                ]
            )->json();

            if (!is_array($responseData)) {
                return [];
            }

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
                $memberOf[] = $groupEntry['id'];
            }

            // apply mapping
            $groupMapping = $this->configReader->v('VootAcl', 'aclMapping', false, []);
            if (!is_array($groupMapping)) {
                return [];
            }

            $returnGroups = [];
            foreach ($memberOf as $groupEntry) {
                // check if it is available in the mapping
                if (array_key_exists($groupEntry, $groupMapping)) {
                    $returnGroups = array_merge($returnGroups, $groupMapping[$groupEntry]);
                }
            }

            return $returnGroups;
        } catch (TransferException $e) {
            return [];
        }
    }
}
