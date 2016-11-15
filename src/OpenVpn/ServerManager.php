<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Server\OpenVpn;

use Psr\Log\LoggerInterface;
use SURFnet\VPN\Server\OpenVpn\Exception\ManagementSocketException;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\ProfileConfig;

/**
 * Manage all OpenVPN processes controlled by this service.
 */
class ServerManager
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var ManagementSocketInterface */
    private $managementSocket;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(Config $config, ManagementSocketInterface $managementSocket, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->managementSocket = $managementSocket;
        $this->logger = $logger;
    }

    /**
     * Get the connection information about connected clients.
     */
    public function connections()
    {
        $clientConnections = [];

        // loop over all profiles
        foreach (array_keys($this->config->v('vpnProfiles')) as $profileId) {
            $profileConfig = new ProfileConfig($this->config->v('vpnProfiles', $profileId));
            $managementIp = $this->getManagementIp($profileConfig);

            $profileConnections = [];
            // loop over all processes
            for ($i = 0; $i < $profileConfig->v('processCount'); ++$i) {
                // add all connections from this instance to profileConnections
                try {
                    // open the socket connection
                    $this->managementSocket->open(
                        sprintf(
                            'tcp://%s:%d',
                            $managementIp,
                            11940 + $i
                        )
                    );
                    $profileConnections = array_merge(
                        $profileConnections,
                        StatusParser::parse($this->managementSocket->command('status 2'))
                    );
                    // close the socket connection
                    $this->managementSocket->close();
                } catch (ManagementSocketException $e) {
                    // we log the error, but continue with the next instance
                    $this->logger->error(
                        sprintf(
                            'error with socket "%s:%s", message: "%s"',
                            $managementIp,
                            11940 + $i,
                            $e->getMessage()
                        )
                    );
                }
            }
            // we add the profileConnections to the clientConnections array
            $clientConnections[] = ['id' => $profileId, 'connections' => $profileConnections];
        }

        return $clientConnections;
    }

    /**
     * Disconnect all clients with this CN from all profiles and instances
     * managed by this service.
     *
     * @param string $commonName the CN to kill
     */
    public function kill($commonName)
    {
        $clientsKilled = 0;
        // loop over all profiles
        foreach (array_keys($this->config->v('vpnProfiles')) as $profileId) {
            $profileConfig = new ProfileConfig($this->config->v('vpnProfiles', $profileId));
            $managementIp = $this->getManagementIp($profileConfig);

            // loop over all processes
            for ($i = 0; $i < $profileConfig->v('processCount'); ++$i) {
                // add all kills from this instance to profileKills
                try {
                    // open the socket connection
                    $this->managementSocket->open(
                        sprintf(
                            'tcp://%s:%d',
                            $managementIp,
                            11940 + $i
                        )
                    );

                    $response = $this->managementSocket->command(sprintf('kill %s', $commonName));
                    if (0 === mb_strpos($response[0], 'SUCCESS: ')) {
                        ++$clientsKilled;
                    }
                    // close the socket connection
                    $this->managementSocket->close();
                } catch (ManagementSocketException $e) {
                    // we log the error, but continue with the next instance
                    $this->logger->error(
                        sprintf(
                            'error with socket "%s:%s", message: "%s"',
                            $managementIp,
                            11940 + $i,
                            $e->getMessage()
                        )
                    );
                }
            }
        }

        return 0 !== $clientsKilled;
    }

    private function getManagementIp(ProfileConfig $profileConfig)
    {
        if('auto' === $profileConfig->v('managementIp')) {
            return sprintf('10.42.%d.%d', 100 + $this->config->v('instanceNumber'), 100 + $profileConfig->v('profileNumber'));
        }

        return $profileConfig->v('managementIp');
    }
}
