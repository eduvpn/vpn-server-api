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

use fkooman\Config\Reader;
use fkooman\Config\YamlFile;
use PHPUnit_Framework_TestCase;

class FirewallTest extends PHPUnit_Framework_TestCase
{
    private function getPoolsConfig($poolId = 'internet', array $configOverride = [])
    {
        $poolsConfigReader = new Reader(
           new YamlFile(dirname(dirname(dirname(dirname(__DIR__)))).'/config/pools.yaml.example')
        );

        $poolsConfig = $poolsConfigReader->v('pools');
        $poolsConfig[$poolId] = array_merge($poolsConfig[$poolId], $configOverride);

        return new Pools($poolsConfig);
    }

    public function testDefaultFirewall()
    {
        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s 10.93.163.0/24 -o eth0 -j MASQUERADE',
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
                '-N vpn-internet',
                '-A FORWARD -i tun-internet+ -s 10.93.163.0/24 -j vpn-internet',
                '-A vpn-internet -o eth0 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp-host-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall4($this->getPoolsConfig(), true)
        );

        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s fdbc:dfcc:f434:e740::/60 -o eth0 -j MASQUERADE',
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
                '-N vpn-internet',
                '-A FORWARD -i tun-internet+ -s fdbc:dfcc:f434:e740::/60 -j vpn-internet',
                '-A vpn-internet -o eth0 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp6-adm-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall6($this->getPoolsConfig(), true)
        );
    }

    public function testBlockSmb()
    {
        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s 10.93.163.0/24 -o eth0 -j MASQUERADE',
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
                '-N vpn-internet',
                '-A FORWARD -i tun-internet+ -s 10.93.163.0/24 -j vpn-internet',
                '-A vpn-internet -o eth0 -m multiport -p tcp --dports 137:139,445 -j REJECT --reject-with icmp-host-prohibited',
                '-A vpn-internet -o eth0 -m multiport -p udp --dports 137:139,445 -j REJECT --reject-with icmp-host-prohibited',
                '-A vpn-internet -o eth0 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp-host-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall4($this->getPoolsConfig('internet', ['blockSmb' => true]), true)
        );
    }

    public function testNoForward6()
    {
        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s 10.93.163.0/24 -o eth0 -j MASQUERADE',
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
                '-N vpn-internet',
                '-A FORWARD -i tun-internet+ -s 10.93.163.0/24 -j vpn-internet',
                '-A vpn-internet -o eth0 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp-host-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall4($this->getPoolsConfig('internet', ['forward6' => false]), true)
        );

        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s fdbc:dfcc:f434:e740::/60 -o eth0 -j MASQUERADE',
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
                '-A FORWARD -j REJECT --reject-with icmp6-adm-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall6($this->getPoolsConfig('internet', ['forward6' => false]), true)
        );
    }

    public function testNoDefaultGateway()
    {
        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s 10.93.163.0/24 -o eth0 -j MASQUERADE',
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
                '-N vpn-internet',
                '-A FORWARD -i tun-internet+ -s 10.93.163.0/24 -j vpn-internet',
                '-A vpn-internet -o eth0 -d 192.168.42.0/24 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp-host-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall4(
                $this->getPoolsConfig(
                    'internet',
                    [
                        'defaultGateway' => false,
                        'routes' => [
                            '192.168.42.0/24',
                            'fd00:1234:1234::/48',
                        ],
                    ]
                ),
                true
            )
        );

        $this->assertSame(
            [
                '*nat',
                ':PREROUTING ACCEPT [0:0]',
                ':INPUT ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
                ':POSTROUTING ACCEPT [0:0]',
                '-A POSTROUTING -s fdbc:dfcc:f434:e740::/60 -o eth0 -j MASQUERADE',
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
                '-N vpn-internet',
                '-A FORWARD -i tun-internet+ -s fdbc:dfcc:f434:e740::/60 -j vpn-internet',
                '-A vpn-internet -o eth0 -d fd00:1234:1234::/48 -j ACCEPT',
                '-A FORWARD -j REJECT --reject-with icmp6-adm-prohibited',
                'COMMIT',
            ],
            Firewall::getFirewall6(
                $this->getPoolsConfig(
                    'internet',
                    [
                        'defaultGateway' => false,
                        'routes' => [
                            '192.168.42.0/24',
                            'fd00:1234:1234::/48',
                        ],
                    ]
                ),
                true
            )
        );
    }
}
