<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\OpenVpn;

use LC\OpenVpn\ConnectionManager;
use LC\OpenVpn\ManagementSocketInterface;
use Psr\Log\LoggerInterface;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\ProfileConfig;

/**
 * Manage all OpenVPN processes controlled by this service.
 */
class ServerManager
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \LC\OpenVpn\ManagementSocketInterface */
    private $managementSocket;

    public function __construct(Config $config, LoggerInterface $logger, ManagementSocketInterface $managementSocket)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->managementSocket = $managementSocket;
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
            $socketAddressList = [];
            for ($i = 0; $i < count($profileConfig->getItem('vpnProtoPorts')); ++$i) {
                $socketAddressList[] = sprintf(
                    'tcp://%s:%d',
                    $managementIp,
                    11940 + $this->toPort($instanceNumber, $profileNumber, $i)
                );
            }

            $connectionManager = new ConnectionManager($socketAddressList, $this->logger, $this->managementSocket);
            $profileConnections += $connectionManager->connections();
            $clientConnections[] = ['id' => $profileId, 'connections' => $profileConnections];
        }

        return $clientConnections;
    }

    /**
     * @param string $commonName
     *
     * @return int
     */
    public function kill($commonName)
    {
        $instanceNumber = $this->config->getItem('instanceNumber');

        // loop over all profiles
        foreach (array_keys($this->config->getSection('vpnProfiles')->toArray()) as $profileId) {
            $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
            $managementIp = $profileConfig->getItem('managementIp');
            $profileNumber = $profileConfig->getItem('profileNumber');

            $socketAddressList = [];
            for ($i = 0; $i < count($profileConfig->getItem('vpnProtoPorts')); ++$i) {
                $socketAddressList[] = sprintf(
                    'tcp://%s:%d',
                    $managementIp,
                    11940 + $this->toPort($instanceNumber, $profileNumber, $i)
                );
            }

            $connectionManager = new ConnectionManager($socketAddressList, $this->logger, $this->managementSocket);

            return $connectionManager->disconnect([$commonName]);
        }

        return 0;
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
