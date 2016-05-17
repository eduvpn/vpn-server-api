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

namespace fkooman\VPN\Server\OpenVpn;

use fkooman\VPN\Server\Pools;
use Psr\Log\LoggerInterface;
use fkooman\VPN\Server\OpenVpn\Exception\ManagementSocketException;

/**
 * Manage all OpenVPN servers controlled by this service using each instance's
 * ServerApi.
 */
class ServerManager
{
    /** @var array */
    private $pools;

    /** @var ManagementSocketInterface */
    private $managementSocket;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(Pools $pools, ManagementSocketInterface $managementSocket, LoggerInterface $logger)
    {
        $this->pools = $pools;
        $this->managementSocket = $managementSocket;
        $this->logger = $logger;
    }

    /**
     * Get the connection information about connected clients.
     */
    public function connections()
    {
        $clientConnections = [];
        // loop over all pools
        foreach ($this->pools as $pool) {
            $poolConnections = [];
            // loop over all instances
            foreach ($pool->getInstances() as $instance) {
                // add all connections from this instance to poolConnections
                try {
                    // open the socket connection
                    $this->managementSocket->open(
                        sprintf(
                            'tcp://%s:%d',
                            $pool->getManagementIp()->getAddress(),
                            $instance->getManagementPort()
                        )
                    );
                    $poolConnections = array_merge(
                        $poolConnections,
                        StatusParser::parse($this->managementSocket->command('status 2'))
                    );
                    // close the socket connection
                    $this->managementSocket->close();
                } catch (ManagementSocketException $e) {
                    // we log the error, but continue with the next instance
                    $this->logger->error(
                        sprintf(
                            'error with socket "%s:%s", message: "%s"',
                            $pool->getManagementIp()->getAddress(),
                            $instance->getManagementPort(),
                            $e->getMessage()
                        )
                    );
                }
            }
            // we add the poolConnections to the clientConnections array
            $clientConnections[] = ['id' => $pool->getId(), 'connections' => $poolConnections];
        }

        return ['data' => $clientConnections];
    }

    /**
     * Disconnect all clients with this CN from all pools and instances 
     * managed by this service.
     *
     * @param string $commonName the CN to kill
     */
    public function kill($commonName)
    {
        $clientsKills = [];
        // loop over all pools
        foreach ($this->pools as $pool) {
            $poolKill = 0;
            // loop over all instances
            foreach ($pool->getInstances() as $instance) {
                // add all kills from this instance to poolKills
                try {
                    // open the socket connection
                    $this->managementSocket->open(
                        sprintf(
                            'tcp://%s:%d',
                            $pool->getManagementIp()->getAddress(),
                            $instance->getManagementPort()
                        )
                    );

                    $response = $this->managementSocket->command(sprintf('kill %s', $commonName));
                    if (0 === strpos($response[0], 'SUCCESS: ')) {
                        ++$poolKill;
                    }
                    // close the socket connection
                    $this->managementSocket->close();
                } catch (ManagementSocketException $e) {
                    // we log the error, but continue with the next instance
                    $this->logger->error(
                        sprintf(
                            'error with socket "%s:%s", message: "%s"',
                            $pool->getManagementIp()->getAddress(),
                            $instance->getManagementPort(),
                            $e->getMessage()
                        )
                    );
                }
            }
            // we add the poolKill to the clientsKill array
            $clientsKills[] = ['id' => $pool->getId(), 'killCount' => $poolKill];
        }

        return ['data' => $clientsKills];
    }
}
