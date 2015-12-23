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
    private $servers;

    public function __construct(array $servers)
    {
        $this->servers = array();
        foreach ($servers as $server) {
            try {
                $socket = new Socket($server['socket']);
            } catch (RuntimeException $e) {
                $socket = false;
            }
            $this->servers[] = array(
                'id' => $server['id'],
                'socket' => $socket,
                'name' => $server['name'],
            );
        }
    }

    public function __destruct()
    {
        foreach ($this->servers as $server) {
            if (false !== $server['socket']) {
                $server['socket']->close();
            }
        }
    }

    public function getConnections()
    {
        $connections = array();

        foreach ($this->servers as $server) {
            if (false === $server['socket']) {
                // cannot connect to OpenVpn instance, instance is dead
                $connections[] = array(
                    'id' => $server['id'],
                    'name' => $server['name'],
                    'clients' => array(),
                );
            } else {
                $api = new Api($server['socket']);
                $connections[] = array(
                    'id' => $server['id'],
                    'name' => $server['name'],
                    'clients' => $api->getStatus(),
                );
            }
        }

        return array('items' => $connections);
    }

    public function getServerInfo()
    {
        $serverInfo = array();
        foreach ($this->servers as $server) {
            if (false === $server['socket']) {
                // cannot connect to OpenVpn instance, instance is dead
                $serverInfo[] = array(
                    'id' => $server['id'],
                    'name' => $server['name'],
                    'up' => false,
                );
            } else {
                $api = new Api($server['socket']);
                $info = $api->getLoadStats();
                $info['id'] = $server['id'];
                $info['name'] = $server['name'];
                $info['up'] = true;
                $info['version'] = $api->getVersion();
                $serverInfo[] = $info;
            }
        }

        return array('items' => $serverInfo);
    }

    public function killClient($id, $commonName)
    {
        foreach ($this->servers as $server) {
            if ($id === $server['id']) {
                $api = new Api($server['socket']);

                return $api->killClient($commonName);
            }
        }

        return false;
    }
}
