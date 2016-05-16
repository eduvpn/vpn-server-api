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

namespace fkooman\VPN\Server\OpenVpn;

use fkooman\VPN\Server\Pools;

/**
 * Manage all OpenVPN servers controlled by this service using each instance's
 * ServerApi.
 */
class ServerManager
{
    /** @var array */
    private $pools;

    public function __construct(Pools $pools)
    {
        $this->pools = $pools;
    }

    /**
     * Get the connection information about connected clients.
     *
     * @return array per server connection information
     */
    public function status()
    {
        $serverStatus = [];
        foreach ($this->pools->getPools() as $pool) {
            $poolInstances = [];
            foreach ($pool->getInstances() as $instance) {
                $socket = sprintf('tcp://%s:%d', $pool->getManagementIp()->getAddress(), $instance->getManagementPort());
                $serverSocket = new ServerSocket($socket);
                $serverApi = new ServerApi($serverSocket);
                if (false !== $status = $serverApi->status()) {
                    $poolInstances[] = $status;
                }
            }
            $serverStatus[] = ['name' => $pool->getName(), 'status' => $poolInstances];
        }

        return array('items' => $serverStatus);
    }

    /**
     * Disconnect all clients with this CN from all servers managed by this
     * service.
     *
     * @param string $commonName the CN to kill
     *
     * @return bool true if >= 1 client was killed, otherwise false
     */
    public function kill($commonName)
    {
        $killStats = array();
        foreach ($this->servers as $server) {
            $killStats[] = $server->kill($commonName);
        }

        return array('items' => $killStats);
    }
}
