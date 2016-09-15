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
namespace SURFnet\VPN\Server\Config;

use SURFnet\VPN\Server\InstanceConfig;
use SURFnet\VPN\Server\PoolConfig;
use SURFnet\VPN\Server\IP;

class Firewall
{
    public static function getFirewall4(array $instanceConfigList, $asArray = false)
    {
        return self::getFirewall($instanceConfigList, 4, $asArray);
    }

    public static function getFirewall6(array $instanceConfigList, $asArray = false)
    {
        return self::getFirewall($instanceConfigList, 6, $asArray);
    }

    private static function getFirewall(array $instanceConfigList, $inetFamily, $asArray)
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
        foreach ($instanceConfigList as $instanceConfig) {
            $firewall = array_merge($firewall, self::getNat($instanceConfig, $inetFamily));
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
        foreach ($instanceConfigList as $instanceConfig) {
            $firewall = array_merge($firewall, self::getForwardChain($instanceConfig, $inetFamily));
        }
        $firewall[] = sprintf('-A FORWARD -j REJECT --reject-with %s', 4 === $inetFamily ? 'icmp-host-prohibited' : 'icmp6-adm-prohibited');
        $firewall[] = 'COMMIT';

        if ($asArray) {
            return $firewall;
        }

        return implode(PHP_EOL, $firewall).PHP_EOL;
    }

    private static function getNat(InstanceConfig $instanceConfig, $inetFamily)
    {
        $nat = [];

        foreach (array_keys($instanceConfig->v('vpnPools')) as $poolNumber => $poolId) {
            $poolConfig = new PoolConfig($instanceConfig->v('vpnPools', $poolId));
            if ($poolConfig->v('useNat')) {
                if (4 === $inetFamily) {
                    // get the IPv4 range
                    $srcNet = $poolConfig->v('range');
                } else {
                    // get the IPv6 range
                    $srcNet = $poolConfig->v('range6');
                }
                // -i (--in-interface) cannot be specified for POSTROUTING
                $nat[] = sprintf('-A POSTROUTING -s %s -o %s -j MASQUERADE', $srcNet, $poolConfig->v('extIf'));
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

    private static function getForwardChain(InstanceConfig $instanceConfig, $inetFamily)
    {
        $forwardChain = [];

        foreach (array_keys($instanceConfig->v('vpnPools')) as $poolNumber => $poolId) {
            $poolConfig = new PoolConfig($instanceConfig->v('vpnPools', $poolId));
            if (6 === $inetFamily && !$poolConfig->v('forward6')) {
                // IPv6 forwarding was disabled
                continue;
            }

            if (4 === $inetFamily) {
                // get the IPv4 range
                $srcNet = $poolConfig->v('range');
            } else {
                // get the IPv6 range
                $srcNet = $poolConfig->v('range6');
            }
            $forwardChain[] = sprintf('-N vpn-%s-%s', $instanceConfig->v('instanceNumber'), $poolNumber);

            $forwardChain[] = sprintf('-A FORWARD -i tun-%s-%s+ -s %s -j vpn-%s-%s', $instanceConfig->v('instanceNumber'), $poolNumber, $srcNet, $instanceConfig->v('instanceNumber'), $poolNumber);

            // merge outgoing forwarding firewall rules to prevent certain
            // traffic
            $forwardChain = array_merge($forwardChain, self::getForwardFirewall($instanceConfig->v('instanceNumber'), $poolNumber, $poolConfig, $inetFamily));

            if ($poolConfig->v('clientToClient')) {
                // allow client-to-client
                $forwardChain[] = sprintf('-A vpn-%s-%s -o tun-%s-%s+ -d %s -j ACCEPT', $instanceConfig->v('instanceNumber'), $poolNumber, $instanceConfig->v('instanceNumber'), $poolNumber, $srcNet);
            }
            if ($poolConfig->v('defaultGateway')) {
                // allow traffic to all outgoing destinations
                $forwardChain[] = sprintf('-A vpn-%s-%s -o %s -j ACCEPT', $instanceConfig->v('instanceNumber'), $poolNumber, $poolConfig->v('extIf'), $srcNet);
            } else {
                // only allow certain traffic to the external interface
                foreach ($poolConfig->v('routes') as $route) {
                    $routeIp = new IP($route);
                    if ($inetFamily === $routeIp->getFamily()) {
                        $forwardChain[] = sprintf('-A vpn-%s-%s -o %s -d %s -j ACCEPT', $instanceConfig->v('instanceNumber'), $poolNumber, $poolConfig->v('extIf'), $route);
                    }
                }
            }
        }

        return $forwardChain;
    }

    private static function getForwardFirewall($instanceNumber, $poolNumber, PoolConfig $poolConfig, $inetFamily)
    {
        $forwardFirewall = [];

        var_dump($poolConfig);

        if ($poolConfig->v('blockSmb')) {
            // drop SMB outgoing traffic
            // @see https://medium.com/@ValdikSS/deanonymizing-windows-users-and-capturing-microsoft-and-vpn-accounts-f7e53fe73834
            foreach (['tcp', 'udp'] as $proto) {
                $forwardFirewall[] = sprintf(
                    '-A vpn-%s-%s -o %s -m multiport -p %s --dports 137:139,445 -j REJECT --reject-with %s',
                    $instanceNumber,
                    $poolNumber,
                    $poolConfig->v('extIf'),
                    $proto,
                    4 === $inetFamily ? 'icmp-host-prohibited' : 'icmp6-adm-prohibited');
            }
        }

        return $forwardFirewall;
    }
}
