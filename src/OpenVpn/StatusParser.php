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
 */
class StatusParser
{
    public static function parse(array $statusData)
    {
        $clientList = [];

        // find "HEADER,CLIENT_LIST"
        $i = 0;
        while (0 !== strpos($statusData[$i], 'HEADER,CLIENT_LIST')) {
            ++$i;
        }
        $clientKeys = array_slice(str_getcsv($statusData[$i]), 2);
        ++$i;
        // iterate over all CLIENT_LIST entries
        while (0 === strpos($statusData[$i], 'CLIENT_LIST')) {
            $clientValues = str_getcsv($statusData[$i]);
            array_shift($clientValues);
            $clientInfo = array_combine($clientKeys, $clientValues);
            $clientList[] = [
                'common_name' => $clientInfo['Common Name'],
                'virtual_address' => [
                    $clientInfo['Virtual Address'],
                    $clientInfo['Virtual IPv6 Address'],
                ],
            ];
            ++$i;
        }

        return $clientList;
    }
}
