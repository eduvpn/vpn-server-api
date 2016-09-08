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
        //TITLE,OpenVPN 2.3.9 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Dec 16 2015
        //TIME,Wed Dec 23 12:52:08 2015,1450875128
        //HEADER,CLIENT_LIST,Common Name,Real Address,Virtual Address,Bytes Received,Bytes Sent,Connected Since,Connected Since (time_t),Username
        //CLIENT_LIST,fkooman_ziptest,::ffff:91.64.87.183,10.42.42.2,127707,127903,Wed Dec 23 12:49:15 2015,1450874955,UNDEF
        //CLIENT_LIST,sebas_tuxed_SGS6,::ffff:83.83.194.107,10.42.42.3,127229,180419,Wed Dec 23 12:05:28 2015,1450872328,UNDEF
        //HEADER,ROUTING_TABLE,Virtual Address,Common Name,Real Address,Last Ref,Last Ref (time_t)
        //ROUTING_TABLE,10.42.42.2,fkooman_ziptest,::ffff:91.64.87.183,Wed Dec 23 12:52:07 2015,1450875127
        //ROUTING_TABLE,fd00:4242:4242::1000,fkooman_ziptest,::ffff:91.64.87.183,Wed Dec 23 12:50:42 2015,1450875042
        //ROUTING_TABLE,fd00:4242:4242::1001,sebas_tuxed_SGS6,::ffff:83.83.194.107,Wed Dec 23 12:28:53 2015,1450873733
        //ROUTING_TABLE,10.42.42.3,sebas_tuxed_SGS6,::ffff:83.83.194.107,Wed Dec 23 12:50:46 2015,1450875046
        //GLOBAL_STATS,Max bcast/mcast queue length,0
        //END

        // for now, we log all statusData to get a good corpus for writing
        // tests

        //error_log(json_encode($statusData));

        $clientListStart = 0;
        $routingTableStart = 0;
        $globalStatsStart = 0;

        for ($i = 0; $i < sizeof($statusData); ++$i) {
            if (0 === strpos($statusData[$i], 'HEADER,CLIENT_LIST')) {
                $clientListStart = $i;
            }
            if (0 === strpos($statusData[$i], 'HEADER,ROUTING_TABLE')) {
                $routingTableStart = $i;
            }
            if (0 === strpos($statusData[$i], 'GLOBAL_STATS')) {
                $globalStatsStart = $i;
            }
        }

        $parsedClientList = self::parseClientList(array_slice($statusData, $clientListStart, $routingTableStart - $clientListStart));
        $parsedRoutingTable = self::parseRoutingTable(array_slice($statusData, $routingTableStart, $globalStatsStart - $routingTableStart));

        // merge routing table in client list
        foreach ($parsedClientList as $key => $value) {
            if (!array_key_exists($key, $parsedRoutingTable)) {
                $parsedClientList[$key]['virtual_address'] = array();
            } else {
                $parsedClientList[$key]['virtual_address'] = $parsedRoutingTable[$key];
            }
        }

        return array_values($parsedClientList);
    }

    private static function parseClientList(array $clientList)
    {
        //HEADER,CLIENT_LIST,Common Name,Real Address,Virtual Address,Bytes Received,Bytes Sent,Connected Since,Connected Since (time_t),Username
        //CLIENT_LIST,fkooman_ziptest,::ffff:91.64.87.183,10.42.42.2,127707,127903,Wed Dec 23 12:49:15 2015,1450874955,UNDEF
        //CLIENT_LIST,sebas_tuxed_SGS6,::ffff:83.83.194.107,10.42.42.3,127229,180419,Wed Dec 23 12:05:28 2015,1450872328,UNDEF
        $parsedClientList = array();
        for ($i = 1; $i < sizeof($clientList); ++$i) {
            $parsedClient = str_getcsv($clientList[$i]);
            $commonName = $parsedClient[1];
            if (array_key_exists($commonName, $parsedClientList)) {
                //syslog(LOG_ERR('duplicate common name, possibly --duplicate-cn enabled in server configuration'));
            }
            $parsedClientList[$commonName] = array(
                'common_name' => $commonName,
                'user_id' => explode('_', $commonName, 2)[0],
                'name' => explode('_', $commonName, 2)[1],
                'real_address' => $parsedClient[2],
                //'virtual_address' => $parsedClient[3],
                'bytes_in' => intval($parsedClient[4]),
                'bytes_out' => intval($parsedClient[5]),
                'connected_since' => intval($parsedClient[7]),
            );
        }

        return $parsedClientList;
    }

    private static function parseRoutingTable(array $routingTable)
    {
        //HEADER,ROUTING_TABLE,Virtual Address,Common Name,Real Address,Last Ref,Last Ref (time_t)
        //ROUTING_TABLE,10.42.42.2,fkooman_ziptest,::ffff:91.64.87.183,Wed Dec 23 12:52:07 2015,1450875127
        //ROUTING_TABLE,fd00:4242:4242::1000,fkooman_ziptest,::ffff:91.64.87.183,Wed Dec 23 12:50:42 2015,1450875042
        //ROUTING_TABLE,fd00:4242:4242::1001,sebas_tuxed_SGS6,::ffff:83.83.194.107,Wed Dec 23 12:28:53 2015,1450873733
        //ROUTING_TABLE,10.42.42.3,sebas_tuxed_SGS6,::ffff:83.83.194.107,Wed Dec 23 12:50:46 2015,1450875046
        $parsedRoutingTable = array();
        for ($i = 1; $i < sizeof($routingTable); ++$i) {
            $parsedRoute = str_getcsv($routingTable[$i]);
            $commonName = $parsedRoute[2];
            if (!array_key_exists($commonName, $parsedRoutingTable)) {
                $parsedRoutingTable[$commonName] = array();
            }
            $parsedRoutingTable[$commonName][] = $parsedRoute[1];
        }

        return $parsedRoutingTable;
    }
}
