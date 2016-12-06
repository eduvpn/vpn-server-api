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

/**
 * Parses the response from the OpenVPN `status 2` command.
 *
 * NOTE: The OpenVPN instance MUST NOT have --duplicate-cn in the configuration
 * as we do not deal with multiple connections with the same CN, due to bugs in
 * udp6 status report where the client port is not mentioned in the
 * 'Real Address' column
 */
class StatusParser
{
    public static function parse(array $statusData)
    {
        $clientListStart = 0;
        $routingTableStart = 0;
        $globalStatsStart = 0;

        for ($i = 0; $i < count($statusData); ++$i) {
            if (0 === mb_strpos($statusData[$i], 'HEADER,CLIENT_LIST')) {
                $clientListStart = $i;
            }
            if (0 === mb_strpos($statusData[$i], 'HEADER,ROUTING_TABLE')) {
                $routingTableStart = $i;
            }
            if (0 === mb_strpos($statusData[$i], 'GLOBAL_STATS')) {
                $globalStatsStart = $i;
            }
        }

        $parsedClientList = self::parseClientList(array_slice($statusData, $clientListStart, $routingTableStart - $clientListStart));
        $parsedRoutingTable = self::parseRoutingTable(array_slice($statusData, $routingTableStart, $globalStatsStart - $routingTableStart));

        // merge routing table in client list
        foreach (array_keys($parsedClientList) as $key) {
            if (!array_key_exists($key, $parsedRoutingTable)) {
                $parsedClientList[$key]['virtual_address'] = [];
            } else {
                $parsedClientList[$key]['virtual_address'] = $parsedRoutingTable[$key];
            }
        }

        return array_values($parsedClientList);
    }

    private static function parseClientList(array $clientList)
    {
        $parsedClientList = [];
        for ($i = 1; $i < count($clientList); ++$i) {
            $parsedClient = str_getcsv($clientList[$i]);
            $commonName = $parsedClient[1];
            if (array_key_exists($commonName, $parsedClientList)) {
                //syslog(LOG_ERR('duplicate common name, possibly --duplicate-cn enabled in server configuration'));
            }
            $parsedClientList[$commonName] = [
                'common_name' => $commonName,
                'proto' => 3 === substr_count($parsedClient[2], '.') ? 4 : 6,
            ];
        }

        return $parsedClientList;
    }

    private static function parseRoutingTable(array $routingTable)
    {
        $parsedRoutingTable = [];
        for ($i = 1; $i < count($routingTable); ++$i) {
            $parsedRoute = str_getcsv($routingTable[$i]);
            $commonName = $parsedRoute[2];
            if (!array_key_exists($commonName, $parsedRoutingTable)) {
                $parsedRoutingTable[$commonName] = [];
            }
            $parsedRoutingTable[$commonName][] = $parsedRoute[1];
        }

        return $parsedRoutingTable;
    }
}
