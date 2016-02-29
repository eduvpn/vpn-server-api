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

use InvalidArgumentException;

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
    public static function getIp4($startIp, $endIp, $usedIp = array())
    {
        $s = ip2long($startIp);
        $e = ip2long($endIp);

        $search = $s;
        while (in_array(long2ip($search), $usedIp)) {
            ++$search;
            if ($search > $e) {
                return false;
            }
        }

        return long2ip($search);
    }

    /**
     * Give a matching IPv6 address for the obtained IPv4 address.
     *
     * @param string $v6 the expanded first 64 bits of an IPv6 address 
     *                   (network), e.g. "fd00:4242:4242:4242"
     * @param string $v4 the IPv4 address to use in the IPv6 address
     *
     * @return string the IPv6 address containing the IPv4 address
     */
    public static function getIp6($v6, $v4)
    {
        if (3 !== substr_count($v6, ':')) {
            throw new InvalidArgumentException('specify the expanded network part of a /64 IPv6 address');
        }
        if (3 !== substr_count($v4, '.')) {
            throw new InvalidArgumentException('invalid IPv4 address');
        }

        $v6e = explode(':', $v6);
        $v4e = explode('.', $v4);

        $v4v6 = sprintf(
            '%s:%s:%s:%s:%s:%s:%s:%s', $v6e[0], $v6e[1], $v6e[2], $v6e[3], $v4e[0], $v4e[1], $v4e[2], $v4e[3]
        );

        return $v4v6;
    }
}

#try {
#    $ip4 = AddressPool::getIp4(
#        '10.42.42.128',
#        '10.42.42.132',
#        array(
#            '10.42.42.128',
#            '10.42.42.129',
#            '10.42.42.130',
#            '10.42.42.131',
#            //'10.42.42.132',
#        )
#    );

#    $ip6 = AddressPool::getIp6(
#        'fd00:4242:4242:4242',
#        $ip4
#    );

#    echo sprintf('IPv4: %s', $ip4).PHP_EOL;
#    echo sprintf('IPv6: %s', $ip6).PHP_EOL;
#} catch (Exception $e) {
#    echo $e->getMessage().PHP_EOL;
#    exit(1);
#}
