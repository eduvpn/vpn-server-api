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

class StatusParser
{
    /** @var string */
    private $socketId;

    /** @var array */
    private $clientList;

    /** @var array */
    private $routingTable;

    public function __construct($socketId, $statusData)
    {
        $this->socketId = $socketId;
        $this->clientList = self::getClientList($statusData);
        $this->routingTable = self::getRoutingTable($statusData, count($this->clientList) + 5);
    }

    public function getClientInfo()
    {
        // combine the data from clientList and routingTable and return it
        // XXX: what if one ID is connected multiple times?

        $clientData = array();
        foreach ($this->clientList as $clientInfo) {
            list($clientId, $clientIpPort, $bytesReceived, $bytesSent, $connectedSince) = explode(',', $clientInfo);
            // XXX: what about native IPv6 connections to OpenVPN?
            list($clientIp, $clientPort) = explode(':', $clientIpPort);

            $clientData[$clientId] = array(
                'bytes_received' => intval($bytesReceived),
                'bytes_sent' => intval($bytesSent),
                'client_ip' => $clientIp,
                'common_name' => $clientId,
                'connected_since' => strtotime(trim($connectedSince)),
                'socket_id' => $this->socketId,
                'vpn_ip' => array(),
            );
        }

        // walk through routing table and add vpn IPs
        foreach ($this->routingTable as $routingInfo) {
            list($vpnIp, $clientId) = explode(',', $routingInfo);
            $clientData[$clientId]['vpn_ip'][] = $vpnIp;
        }

        // now turn this into a normal array with numeric index
        $asArray = array(
            //'connectedClients' => array(),
        );

        foreach ($clientData as $k => $v) {
            $asArray[] = $v;
        }

        return $asArray;
    }

    private static function getClientList($statusData)
    {
        $splitData = self::splitData($statusData);

        // clientList always starts at index 3
        $clientList = array();
        $i = 3;
        // walk through the list until we hit 'ROUTING TABLE'
        while (0 !== strpos($splitData[$i], 'ROUTING TABLE')) {
            $clientList[] = $splitData[$i];
            ++$i;
        }

        return $clientList;
    }

    private static function getRoutingTable($statusData, $startIndex)
    {
        $splitData = self::splitData($statusData);
        $routingTable = array();
        $i = $startIndex;
        // walk through the list until we hit 'GLOBAL STATS'
        while (0 !== strpos($splitData[$i], 'GLOBAL STATS')) {
            $routingTable[] = $splitData[$i];
            ++$i;
        }

        return $routingTable;
    }

    private static function splitData($statusData)
    {
        return explode("\n", $statusData);
    }
}
