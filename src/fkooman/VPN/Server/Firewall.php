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

class Firewall
{
    public static function getFirewall4(array $instanceList, $asArray = false)
    {
        return self::getFirewall($instanceList, 4, $asArray);
    }

    public static function getFirewall6(array $instanceList, $asArray = false)
    {
        return self::getFirewall($instanceList, 6, $asArray);
    }

    private static function getFirewall(array $instanceList, $inetFamily, $asArray)
    {
        $firewall = [];

        // NAT
        $firewall = array_merge(
            $firewall,
             [
            '*nat',
            ':PREROUTING ACCEPT [0:0]',
            ':INPUT ACCEPT [0:0]',
            ':OUTPUT ACCEPT [0:0]',
            ':POSTROUTING ACCEPT [0:0]',
            ]
        );
        // add all instances
        foreach ($instanceList as $instanceId => $p) {
            $firewall = array_merge($firewall, self::getNat($p, $inetFamily));
        }
        $firewall[] = 'COMMIT';

        // FILTER
        $firewall = array_merge(
            $firewall,
            [
                '*filter',
                ':INPUT ACCEPT [0:0]',
                ':FORWARD ACCEPT [0:0]',
                ':OUTPUT ACCEPT [0:0]',
            ]
        );

        // INPUT
        $firewall = array_merge($firewall, self::getInputChain($inetFamily));

        // FORWARD
        $firewall = array_merge(
            $firewall,
            [
                //sprintf('-A FORWARD -p %s -j ACCEPT', 4 === $inetFamily ? 'icmp' : 'ipv6-icmp'),
                '-A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT',
            ]
        );

        // add all instances
        foreach ($instanceList as $instanceId => $p) {
            $firewall = array_merge($firewall, self::getForwardChain($instanceId, $p, $inetFamily));
        }
        $firewall[] = sprintf('-A FORWARD -j REJECT --reject-with %s', 4 === $inetFamily ? 'icmp-host-prohibited' : 'icmp6-adm-prohibited');
        $firewall[] = 'COMMIT';

        if ($asArray) {
            return $firewall;
        }

        return implode(PHP_EOL, $firewall).PHP_EOL;
    }

    private static function getNat(Pools $p, $inetFamily)
    {
        $nat = [];

        foreach ($p as $pool) {
            if ($pool->getUseNat()) {
                if (4 === $inetFamily) {
                    // get the IPv4 range
                    $srcNet = $pool->getRange()->getAddressPrefix();
                } else {
                    // get the IPv6 range
                    $srcNet = $pool->getRange6()->getAddressPrefix();
                }
                // -i (--in-interface) cannot be specified for POSTROUTING
                $nat[] = sprintf('-A POSTROUTING -s %s -o %s -j MASQUERADE', $srcNet, $pool->getExtIf());
            }
        }

        return $nat;
    }

    private static function getInputChain($inetFamily)
    {
        $inputChain = [
            '-A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT',
            sprintf('-A INPUT -p %s -j ACCEPT', 4 === $inetFamily ? 'icmp' : 'ipv6-icmp'),
            '-A INPUT -i lo -j ACCEPT',
            '-A INPUT -m state --state NEW -m multiport -p tcp --dports 22,80,443 -j ACCEPT',
            '-A INPUT -m state --state NEW -m udp -p udp --dport 1194:1201 -j ACCEPT',
            sprintf('-A INPUT -j REJECT --reject-with %s', 4 === $inetFamily ? 'icmp-host-prohibited' : 'icmp6-adm-prohibited'),
        ];

        return $inputChain;
    }

    private static function getForwardChain($instanceId, Pools $p, $inetFamily)
    {
        $forwardChain = [];

        foreach ($p as $pool) {
            if (6 === $inetFamily && !$pool->getForward6()) {
                // IPv6 forwarding was disabled
                continue;
            }

            if (4 === $inetFamily) {
                // get the IPv4 range
                $srcNet = $pool->getRange()->getAddressPrefix();
            } else {
                // get the IPv6 range
                $srcNet = $pool->getRange6()->getAddressPrefix();
            }
            $forwardChain[] = sprintf('-N vpn-%s-%s', $instanceId, $pool->getId());

            $forwardChain[] = sprintf('-A FORWARD -i tun-%s-%s+ -s %s -j vpn-%s-%s', $instanceId, $pool->getId(), $srcNet, $instanceId, $pool->getId());

            // merge outgoing forwarding firewall rules to prevent certain
            // traffic
            $forwardChain = array_merge($forwardChain, self::getForwardFirewall($instanceId, $pool, $inetFamily));

            if ($pool->getClientToClient()) {
                // allow client-to-client
                $forwardChain[] = sprintf('-A vpn-%s-%s -o tun-%s-%s+ -d %s -j ACCEPT', $instanceId, $pool->getId(), $instanceId, $pool->getId(), $srcNet);
            }
            if ($pool->getDefaultGateway()) {
                // allow traffic to all outgoing destinations
                $forwardChain[] = sprintf('-A vpn-%s-%s -o %s -j ACCEPT', $instanceId, $pool->getId(), $pool->getExtIf(), $srcNet);
            } else {
                // only allow certain traffic to the external interface
                foreach ($pool->getRoutes() as $route) {
                    if ($inetFamily === $route->getFamily()) {
                        $forwardChain[] = sprintf('-A vpn-%s-%s -o %s -d %s -j ACCEPT', $instanceId, $pool->getId(), $pool->getExtIf(), $route->getAddressPrefix());
                    }
                }
            }
        }

        return $forwardChain;
    }

    private static function getForwardFirewall($instanceId, Pool $pool, $inetFamily)
    {
        $forwardFirewall = [];

        if ($pool->getBlockSmb()) {
            // drop SMB outgoing traffic
            // @see https://medium.com/@ValdikSS/deanonymizing-windows-users-and-capturing-microsoft-and-vpn-accounts-f7e53fe73834
            foreach (['tcp', 'udp'] as $proto) {
                $forwardFirewall[] = sprintf(
                    '-A vpn-%s-%s -o %s -m multiport -p %s --dports 137:139,445 -j REJECT --reject-with %s',
                    $instanceId,
                    $pool->getId(),
                    $pool->getExtIf(),
                    $proto,
                    4 === $inetFamily ? 'icmp-host-prohibited' : 'icmp6-adm-prohibited');
            }
        }

        return $forwardFirewall;
    }
}
