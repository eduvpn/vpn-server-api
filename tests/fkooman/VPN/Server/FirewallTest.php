<?php

/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

class FirewallTest extends PHPUnit_Framework_TestCase
{
    public function testFirewall4()
    {
        $this->assertSame(
            [
                0 => '*nat',
                1 => ':PREROUTING ACCEPT [0:0]',
                2 => ':OUTPUT ACCEPT [0:0]',
                3 => ':POSTROUTING ACCEPT [0:0]',
                4 => '-A POSTROUTING -o eth0 -j MASQUERADE',
                5 => 'COMMIT',
                6 => '*filter',
                7 => ':INPUT ACCEPT [0:0]',
                8 => ':FORWARD ACCEPT [0:0]',
                9 => ':OUTPUT ACCEPT [0:0]',
                10 => '-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT',
                11 => '-A INPUT -p icmp -j ACCEPT',
                12 => '-A INPUT -i lo -j ACCEPT',
                13 => '-A INPUT -m state --state NEW -m tcp -p tcp --dport 22 -j ACCEPT',
                14 => '-A INPUT -m state --state NEW -m tcp -p tcp --dport 80 -j ACCEPT',
                15 => '-A INPUT -m state --state NEW -m tcp -p tcp --dport 443 -j ACCEPT',
                16 => '-A INPUT -m state --state NEW -m udp -p udp --dport 1194 -j ACCEPT',
                17 => '-A INPUT -m state --state NEW -m udp -p udp --dport 1195 -j ACCEPT',
                18 => '-A INPUT -m state --state NEW -m udp -p udp --dport 1196 -j ACCEPT',
                19 => '-A INPUT -j REJECT --reject-with icmp-host-prohibited',
                20 => '-A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT',
                21 => '-N vpn-default',
                22 => '-A FORWARD -i tun-default+ -s 10.42.42.0/24 -j vpn-default',
                23 => '-A vpn-default -o eth0 -d 192.168.1.0/24 -j ACCEPT',
                24 => '-A FORWARD -j REJECT --reject-with icmp-host-prohibited',
                25 => 'COMMIT',
            ],
            Firewall::getFirewall4($this->getPools(), 'eth0', true, false, true)
        );
    }

    public function testFirewall6()
    {
        $this->assertSame(
            [
                0 => '*nat',
                1 => ':PREROUTING ACCEPT [0:0]',
                2 => ':OUTPUT ACCEPT [0:0]',
                3 => ':POSTROUTING ACCEPT [0:0]',
                4 => '-A POSTROUTING -o eth0 -j MASQUERADE',
                5 => 'COMMIT',
                6 => '*filter',
                7 => ':INPUT ACCEPT [0:0]',
                8 => ':FORWARD ACCEPT [0:0]',
                9 => ':OUTPUT ACCEPT [0:0]',
                10 => '-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT',
                11 => '-A INPUT -p ipv6-icmp -j ACCEPT',
                12 => '-A INPUT -i lo -j ACCEPT',
                13 => '-A INPUT -m state --state NEW -m tcp -p tcp --dport 22 -j ACCEPT',
                14 => '-A INPUT -m state --state NEW -m tcp -p tcp --dport 80 -j ACCEPT',
                15 => '-A INPUT -m state --state NEW -m tcp -p tcp --dport 443 -j ACCEPT',
                16 => '-A INPUT -m state --state NEW -m udp -p udp --dport 1194 -j ACCEPT',
                17 => '-A INPUT -m state --state NEW -m udp -p udp --dport 1195 -j ACCEPT',
                18 => '-A INPUT -m state --state NEW -m udp -p udp --dport 1196 -j ACCEPT',
                19 => '-A INPUT -j REJECT --reject-with icmp6-adm-prohibited',
                20 => '-A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT',
                21 => '-N vpn-default',
                22 => '-A FORWARD -i tun-default+ -s fd00:4242:4242::/48 -j vpn-default',
                23 => '-A vpn-default -o eth0 -d fd00:1010:1010::/48 -j ACCEPT',
                24 => '-A FORWARD -j REJECT --reject-with icmp6-adm-prohibited',
                25 => 'COMMIT',
            ],
            Firewall::getFirewall6($this->getPools(), 'eth0', true, false, true)
        );
    }

    private function getPools()
    {
        return new Pools(
            [
                'default' => [
                    'name' => 'Default Instance',
                    'hostName' => 'vpn.example',
                    'range' => '10.42.42.0/24',
                    'range6' => 'fd00:4242:4242::/48',
                    'dns' => ['8.8.8.8', '2001:4860:4860::8888'],
                    'routes' => ['192.168.1.0/24', 'fd00:1010:1010::/48'],
                ],
            ]
        );
    }
}
