<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\OpenVpn;

use Psr\Log\LoggerInterface;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\ProfileConfig;
use SURFnet\VPN\Server\OpenVpn\Exception\ManagementSocketException;

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
        $instanceNumber = $this->config->getItem('instanceNumber');

        // loop over all profiles
        foreach (array_keys($this->config->getSection('vpnProfiles')->toArray()) as $profileId) {
            $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
            $managementIp = $profileConfig->getItem('managementIp');
            $profileNumber = $profileConfig->getItem('profileNumber');

            $profileConnections = [];
            // loop over all processes
            for ($i = 0; $i < count($profileConfig->getItem('vpnProtoPorts')); ++$i) {
                // add all connections from this instance to profileConnections
                try {
                    // open the socket connection
                    $this->managementSocket->open(
                        sprintf(
                            'tcp://%s:%d',
                            $managementIp,
                            11940 + $this->toPort($instanceNumber, $profileNumber, $i)
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
                            'error with socket "tcp://%s:%d", message: "%s"',
                            $managementIp,
                            11940 + $this->toPort($instanceNumber, $profileNumber, $i),
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
        $instanceNumber = $this->config->getItem('instanceNumber');

        // loop over all profiles
        foreach (array_keys($this->config->getSection('vpnProfiles')->toArray()) as $profileId) {
            $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
            $managementIp = $profileConfig->getItem('managementIp');
            $profileNumber = $profileConfig->getItem('profileNumber');

            // loop over all processes
            for ($i = 0; $i < count($profileConfig->getItem('vpnProtoPorts')); ++$i) {
                // add all kills from this instance to profileKills
                try {
                    // open the socket connection
                    $this->managementSocket->open(
                        sprintf(
                            'tcp://%s:%d',
                            $managementIp,
                            11940 + $this->toPort($instanceNumber, $profileNumber, $i)
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
                            'error with socket "tcp://%s:%d", message: "%s"',
                            $managementIp,
                            11940 + $this->toPort($instanceNumber, $profileNumber, $i),
                            $e->getMessage()
                        )
                    );
                }
            }
        }

        return 0 !== $clientsKilled;
    }

    private function toPort($instanceNumber, $profileNumber, $processNumber)
    {
        // convert an instanceNumber, $profileNumber and $processNumber to a management port

        // instanceId = 6 bits (max 64)
        // profileNumber = 4 bits (max 16)
        // processNumber = 4 bits  (max 16)
        return ($instanceNumber - 1 << 8) | ($profileNumber - 1 << 4) | ($processNumber);
    }
}
