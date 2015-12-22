<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
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
namespace fkooman\VPN\Server;

class Manage
{
    /** @var array */
    private $socketStatus;

    public function __construct(array $socketAddresses)
    {
        foreach ($socketAddresses as $socketAddress) {
            $this->socketStatus[$socketAddress] = new SocketStatus($socketAddress);
        }
    }

    public function getClientInfo()
    {
        $combinedClientInfo = array();
        foreach ($this->socketStatus as $k => $v) {
            $statusParser = new StatusParser($k, $v->fetchStatus());
            $combinedClientInfo = array_merge($combinedClientInfo, $statusParser->getClientInfo());
        }

        return array('items' => $combinedClientInfo);
    }

    public function getServerInfo()
    {
        $serverInfo = array(
            'items' => array(),
        );

        foreach ($this->socketStatus as $k => $v) {
            $serverInfo['items'][] = array(
                'version' => $v->fetchVersion(),
                'socket' => $k,
            );
        }

        return $serverInfo;
    }

    public function killClient($socketId, $commonName)
    {
        $this->socketStatus[$socketId]->killClient($commonName);
    }
}
