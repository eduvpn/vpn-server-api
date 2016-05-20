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
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s 10.42.42.0/24 -o eth0 -j MASQUERADE',
                'COMMIT',
                '*filter',
                ':INPUT ACCEPT [0:0]',
                ':FORWARD ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                '-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT',
                '-A INPUT -p icmp -j ACCEPT',
                '-A INPUT -i lo -j ACCEPT',
                '-A INPUT -m state --state NEW -m tcp -p tcp --dport 22 -j ACCEPT',
                '-A INPUT -m state --state NEW -m tcp -p tcp --dport 80 -j ACCEPT',
                '-A INPUT -m state --state NEW -m tcp -p tcp --dport 443 -j ACCEPT',
                '-A INPUT -m state --state NEW -m udp -p udp --dport 1194 -j ACCEPT',
                '-A INPUT -m state --state NEW -m udp -p udp --dport 1195 -j ACCEPT',
                '-A INPUT -m state --state NEW -m udp -p udp --dport 1196 -j ACCEPT',
                '-A INPUT -j REJECT --reject-with icmp-host-prohibited',
                '-A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT',
                '-N vpn-default',
                '-A FORWARD -i tun-default+ -s 10.42.42.0/24 -j vpn-default',
                '-A vpn-default -o eth0 -d 192.168.1.0/24 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp-host-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall4($this->getPools(), false, true)
        );
    }

    public function testFirewall6()
    {
        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s fd00:4242:4242::/48 -o eth0 -j MASQUERADE',
                'COMMIT',
                '*filter',
                ':INPUT ACCEPT [0:0]',
                ':FORWARD ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                '-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT',
                '-A INPUT -p ipv6-icmp -j ACCEPT',
                '-A INPUT -i lo -j ACCEPT',
                '-A INPUT -m state --state NEW -m tcp -p tcp --dport 22 -j ACCEPT',
                '-A INPUT -m state --state NEW -m tcp -p tcp --dport 80 -j ACCEPT',
                '-A INPUT -m state --state NEW -m tcp -p tcp --dport 443 -j ACCEPT',
                '-A INPUT -m state --state NEW -m udp -p udp --dport 1194 -j ACCEPT',
                '-A INPUT -m state --state NEW -m udp -p udp --dport 1195 -j ACCEPT',
                '-A INPUT -m state --state NEW -m udp -p udp --dport 1196 -j ACCEPT',
                '-A INPUT -j REJECT --reject-with icmp6-adm-prohibited',
                '-A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT',
                '-N vpn-default',
                '-A FORWARD -i tun-default+ -s fd00:4242:4242::/48 -j vpn-default',
                '-A vpn-default -o eth0 -d fd00:1010:1010::/48 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp6-adm-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall6($this->getPools(), false, true)
        );
    }

    private function getPools()
    {
        return new Pools(
            [
                'default' => [
                    'name' => 'Default Instance',
                    'hostName' => 'vpn.example',
                    'extIf' => 'eth0',
                    'useNat' => true,
                    'range' => '10.42.42.0/24',
                    'range6' => 'fd00:4242:4242::/48',
                    'dns' => ['8.8.8.8', '2001:4860:4860::8888'],
                    'routes' => ['192.168.1.0/24', 'fd00:1010:1010::/48'],
                ],
            ]
        );
    }
}
