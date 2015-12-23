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

use RuntimeException;

class Manage
{
    /** @var array */
    private $sockets;

    public function __construct(array $servers)
    {
        foreach ($servers as $server) {
            try {
                $socket = new Socket($server['socket']);
            } catch (RuntimeException $e) {
                $socket = false;
            }
            $this->sockets[$server['id']] = $socket;
        }
    }

    public function __destruct()
    {
        foreach ($this->sockets as $socket) {
            if (false !== $socket) {
                $socket->close();
            }
        }
    }

    public function getConnections()
    {
        $connections = array();

        foreach ($this->sockets as $id => $socket) {
            if (false === $socket) {
                // cannot connect to OpenVpn instance, instance is dead
                $connections[] = array(
                    'id' => $id,
                    'clients' => array(),
                );
            } else {
                $api = new Api($socket);
                $connections[] = array(
                    'id' => $id,
                    'clients' => $api->getStatus(),
                );
            }
        }

        return array('items' => $connections);
    }

    public function getServerInfo()
    {
        $serverInfo = array();
        foreach ($this->sockets as $id => $socket) {
            if (false === $socket) {
                // cannot connect to OpenVpn instance, instance is dead
                $serverInfo[] = array(
                    'id' => $id,
                    'up' => false,
                );
            } else {
                $api = new Api($socket);
                $info = $api->getLoadStats();
                $info['id'] = $id;
                $info['up'] = true;
                $info['version'] = $api->getVersion();
                $serverInfo[] = $info;
            }
        }

        return array('items' => $serverInfo);
    }

    public function killClient($id, $commonName)
    {
        $api = new Api($this->sockets[$id]);

        return $api->killClient($commonName);
    }
}
