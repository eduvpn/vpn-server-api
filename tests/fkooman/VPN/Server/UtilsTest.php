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

use PHPUnit_Framework_TestCase;

class UtilsTest extends PHPUnit_Framework_TestCase
{
    public function testNormalizeIP()
    {
        $this->assertSame('fd00::1', Utils::normalizeIP('fd00::1'));
        $this->assertSame('fd00::1', Utils::normalizeIP('fd00:0000:0:000::1'));
    }

    public function testConfigDataToOpenVpnDefaultGw()
    {
        $this->assertSame(
            [
                'ifconfig-push 10.42.42.5 255.255.255.0',
                'ifconfig-ipv6-push fd00:4242:4242:1194:10:42:42:5/64 fd00:4242:4242:1194::1',
                'push "redirect-gateway def1 bypass-dhcp"',
                'push "route 0.0.0.0 0.0.0.0"',
                'push "redirect-gateway ipv6"',
                'push "route-ipv6 2000::/3"',
                'push "dhcp-option DNS 8.8.8.8"',
                'push "dhcp-option DNS 8.8.4.4"',
            ],
            Utils::configDataToOpenVpn(
                [
                    'v4' => '10.42.42.5',
                    'v4_netmask' => '255.255.255.0',
                    'v6' => 'fd00:4242:4242:1194:10:42:42:5',
                    'v6_gw' => 'fd00:4242:4242:1194::1',
                    'default_gw' => true,
                    'dns' => ['8.8.8.8', '8.8.4.4'],
                ]
            )
        );
    }

    public function testConfigDataToOpenVpnNoDefaultGw()
    {
        $this->assertSame(
            [
                'ifconfig-push 10.42.42.5 255.255.255.0',
                'ifconfig-ipv6-push fd00:4242:4242:1194:10:42:42:5/64 fd00:4242:4242:1194::1',
                'push "route 192.168.1.0 255.255.255.0"',
                'push "route-ipv6 fd00:1010:1010:1010::/64"',
            ],
            Utils::configDataToOpenVpn(
                [
                    'v4' => '10.42.42.5',
                    'v4_netmask' => '255.255.255.0',
                    'v6' => 'fd00:4242:4242:1194:10:42:42:5',
                    'v6_gw' => 'fd00:4242:4242:1194::1',
                    'default_gw' => false,
                    'dst_net4' => ['192.168.1.0/24'],
                    'dst_net6' => ['fd00:1010:1010:1010::/64'],
                ]
            )
        );
    }
}
