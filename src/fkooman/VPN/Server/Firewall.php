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

class Firewall
{
    public static function getFirewall4(Pools $p, $extIf, $useNat, $disableForward = false, $asArray = false)
    {
        return self::getFirewall($p, 4, $extIf, $useNat, $disableForward, $asArray);
    }

    public static function getFirewall6(Pools $p, $extIf, $useNat, $disableForward = false, $asArray = false)
    {
        return self::getFirewall($p, 6, $extIf, $useNat, $disableForward, $asArray);
    }

    private static function getFirewall(Pools $p, $inetFamily, $extIf, $useNat, $disableForward, $asArray)
    {
        $firewall = [];
        // NAT
        if ($useNat) {
            $firewall = array_merge($firewall, self::getNat($extIf));
        }

        // FILTER
        $firewall = array_merge($firewall, self::getFilter($p, $inetFamily, $extIf, $disableForward));

        if ($asArray) {
            return $firewall;
        }

        return implode(PHP_EOL, $firewall).PHP_EOL;
    }

    private static function getFilter(Pools $p, $inetFamily, $extIf, $disableForward)
    {
        $filter = [
            '*filter',
            ':INPUT ACCEPT [0:0]',
            ':FORWARD ACCEPT [0:0]',
            ':OUTPUT ACCEPT [0:0]',
        ];

        // INPUT
        $filter = array_merge($filter, self::getInputChain($p, $inetFamily));

        // FORWARD
        $filter = array_merge($filter, self::getForwardChain($p, $inetFamily, $extIf, $disableForward));

        $filter[] = 'COMMIT';

        return $filter;
    }

    private static function getInputChain(Pools $p, $inetFamily)
    {
        $inputChain = [
            '-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT',
            sprintf('-A INPUT -p %s -j ACCEPT', 4 === $inetFamily ? 'icmp' : 'ipv6-icmp'),
            '-A INPUT -i lo -j ACCEPT',
        ];

        $inputPorts = self::getIngressPorts($p);
        foreach ($inputPorts as $inputPort) {
            list($proto, $port) = explode('/', $inputPort);
            $inputChain[] = sprintf('-A INPUT -m state --state NEW -m %s -p %s --dport %d -j ACCEPT', $proto, $proto, $port);
        }

        $inputChain[] = sprintf('-A INPUT -j REJECT --reject-with %s', 4 === $inetFamily ? 'icmp-host-prohibited' : 'icmp6-adm-prohibited');

        return $inputChain;
    }

    private static function getForwardChain(Pools $p, $inetFamily, $extIf, $disableForward)
    {
        $forwardChain = [
            '-A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT',
        ];

        if (!$disableForward) {
            foreach ($p as $pool) {
                if (4 === $inetFamily) {
                    // get the IPv4 range
                    $srcNet = $pool->getRange()->getAddressPrefix();
                } else {
                    // get the IPv6 range
                    $srcNet = $pool->getRange6()->getAddressPrefix();
                }
                $forwardChain[] = sprintf('-N vpn-%s', $pool->getId());
                $forwardChain[] = sprintf('-A FORWARD -i tun-%s+ -s %s -j vpn-%s', $pool->getId(), $srcNet, $pool->getId());
                if ($pool->getClientToClient()) {
                    // allow client-to-client
                    $forwardChain[] = sprintf('-A vpn-%s -o tun-%s+ -d %s -j ACCEPT', $pool->getId(), $pool->getId(), $srcNet);
                }
                if ($pool->getDefaultGateway()) {
                    // allow all traffic to the external interface
                    $forwardChain[] = sprintf('-A vpn-%s -o %s -j ACCEPT', $pool->getId(), $extIf, $srcNet);
                } else {
                    // only allow certain traffic to the external interface
                    foreach ($pool->getRoutes() as $route) {
                        if ($inetFamily === $route->getFamily()) {
                            $forwardChain[] = sprintf('-A vpn-%s -o %s -d %s -j ACCEPT', $pool->getId(), $extIf, $route->getAddressPrefix());
                        }
                    }
                }
            }
        }

        $forwardChain[] = sprintf('-A FORWARD -j REJECT --reject-with %s', 4 === $inetFamily ? 'icmp-host-prohibited' : 'icmp6-adm-prohibited');

        return $forwardChain;
    }

    private static function getNat($extIf)
    {
        return [
            '*nat',
            ':PREROUTING ACCEPT [0:0]',
            ':OUTPUT ACCEPT [0:0]',
            ':POSTROUTING ACCEPT [0:0]',
            sprintf('-A POSTROUTING -o %s -j MASQUERADE', $extIf),
            'COMMIT',
        ];
    }

    private static function getIngressPorts(Pools $p)
    {
        $ingressPorts = ['tcp/22', 'tcp/80', 'tcp/443'];

        // we only care about additional UDP ports, as we only want UDP and 
        // fallback to tcp/443
        foreach ($p as $pool) {
            foreach ($pool->getInstances() as $instance) {
                if ('udp' === $instance->getProto()) {
                    $port = sprintf('udp/%d', $instance->getPort());
                    if (!in_array($port, $ingressPorts)) {
                        $ingressPorts[] = $port;
                    }
                }
            }
        }

        return $ingressPorts;
    }
}
