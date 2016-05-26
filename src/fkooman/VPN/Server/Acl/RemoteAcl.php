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

class RemoteAcl implements AclInterface
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
        try {
            $apiUrl = $this->configReader->v('RemoteAcl', 'apiUrl');
            $responseData = $this->client->get($apiUrl)->json();

            if (!isset($responseData['data']['groups'][$userId])) {
                return [];
            }
            $userGroups = $responseData['data']['groups'][$userId];

            if (!is_array($userGroups)) {
                return [];
            }

            $returnGroups = [];
            foreach ($userGroups as $userGroup) {
                if (!is_string($userGroup) || 0 >= strlen($userGroup)) {
                    continue;
                }
                $returnGroups[] = $userGroup;
            }

            return $returnGroups;
        } catch (TransferException $e) {
            return [];
        }
    }
}
