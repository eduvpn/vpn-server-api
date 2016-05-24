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
            $apiBaseUrl = $this->configReader->v('RemoteAcl', 'apiBaseUrl');
            $requestUrl = sprintf('%s/%s', $apiBaseUrl, $userId);
            $responseData = $this->client->get($requestUrl)->json();

            return $responseData['memberOf'];
        } catch (TransferException $e) {
            return [];
        }
    }
}
