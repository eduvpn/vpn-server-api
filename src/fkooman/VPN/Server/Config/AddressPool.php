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

namespace fkooman\VPN\Server\Config;

class AddressPool
{
    /**
     * Find the first free IPv4 address.
     *
     * @param string $startIp the IPv4 address to start from
     * @param string $endIp   the IPv4 address to end at, i.e. limiting the range
     * @param array  $usedIp  array of IPv4 addresses already in use
     *
     * @return string|false the assigned IPv4 address or false when no address
     *                      is available
     */
    public static function getIp4($startIp, $endIp, $usedIp = [])
    {
        $s = ip2long($startIp);
        $e = ip2long($endIp);

        for ($i = $s; $i < $e; ++$i) {
            if (in_array(long2ip($i), $usedIp)) {
                continue;
            }

            return long2ip($i);
        }

        return false;
    }

    /**
     * Give a matching IPv6 address for the obtained IPv4 address.
     *
     * @param string $v6p the expanded first 64 bits of an IPv6 address 
     *                    (network), e.g. "fd00:4242:4242:4242"
     * @param string $v4  the IPv4 address to use in the IPv6 address
     *
     * @return string the IPv6 address containing the IPv4 address
     */
    public static function getIp6($v6p, $v4)
    {
        $v4e = str_split(bin2hex(inet_pton($v4)), 4);

        return sprintf('%s::ffff:%s:%s', $v6p, $v4e[0], $v4e[1]);
    }
}
