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

use Socket\Raw\Exception;

class Manage
{
    /** @var array */
    private $socketStatus;

    public function __construct(array $socketAddresses)
    {
        foreach ($socketAddresses as $socketAddress) {
            try {
                $socketStatus = new SocketStatus($socketAddress);
            } catch (Exception $e) {
                $socketStatus = false;
            }
            $this->socketStatus[$socketAddress] = $socketStatus;
        }
    }

    public function getClientInfo()
    {
        $combinedClientInfo = array();
        foreach ($this->socketStatus as $k => $v) {
            if (false !== $v) {
                $statusParser = new StatusParser($k, $v->fetchStatus());
                $combinedClientInfo = array_merge($combinedClientInfo, $statusParser->getClientInfo());
            }
        }

        return array('items' => $combinedClientInfo);
    }

    public function getServerInfo()
    {
        $serverInfo = array(
            'items' => array(),
        );

        foreach ($this->socketStatus as $k => $v) {
            $info = array(
                'socket' => $k,
                'available' => false !== $v,
            );
            if (false !== $v) {
                $info = array_merge($info, $v->fetchServerInfo());
            }

            $serverInfo['items'][] = $info;
        }

        return $serverInfo;
    }

    public function killClient($socketId, $commonName)
    {
        $this->socketStatus[$socketId]->killClient($commonName);
    }
}
