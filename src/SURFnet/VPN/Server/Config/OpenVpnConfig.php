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
use RuntimeException;

class OpenVpnConfig
{
    /** @var string */
    private $vpnConfigDir;

    public function __construct($vpnConfigDir)
    {
        $this->vpnConfigDir = $vpnConfigDir;
    }

    public function write($instanceId, InstanceConfig $instanceConfig)
    {
        $instanceNumber = $instanceConfig->instanceNumber();
        foreach ($instanceConfig->pools() as $poolNumber => $poolId) {
            $poolConfig = $instanceConfig->pool($poolId);
            $poolConfig->s('instanceId', $instanceId);
            $poolConfig->s('poolId', $poolId);
            $this->writePool($instanceNumber, $poolNumber, $poolConfig);
        }
    }

    private function writePool($instanceNumber, $poolNumber, PoolConfig $poolConfig)
    {
        $range = new IP($poolConfig->v('range'));
        $range6 = new IP($poolConfig->v('range6'));
        $processCount = $poolConfig->getProcessCount();

        $splitRange = $range->split($processCount);
        $splitRange6 = $range6->split($processCount);

        $poolConfig->s('managementIp', sprintf('127.42.%d.%d', 100 + $instanceNumber, 100 + $poolNumber));

        for ($i = 0; $i < $processCount; ++$i) {
            // protocol is udp unless it is the last process when there is
            // not just one process
            if (1 === $processCount || $i !== $processCount - 1) {
                $proto = 'udp';
                $port = 1194 + $i;
            } else {
                $proto = 'tcp';
                $port = 1194;
            }

            $poolConfig->s('range', $splitRange[$i]);
            $poolConfig->s('range6', $splitRange6[$i]);
            $poolConfig->s('dev', sprintf('tun-%d-%d-%d', $instanceNumber, $poolNumber, $i));
            $poolConfig->s('proto', $proto);
            $poolConfig->s('port', $port);
            $poolConfig->s('managementPort', 11940 + $i);
            $poolConfig->s(
                'configName',
                sprintf(
                    'server-%s-%s-%s-%d.conf',
                    $poolConfig->v('instanceId'),
                    $poolConfig->v('poolId'),
                    $poolConfig->v('proto'),
                    $poolConfig->v('port')
                )
            );

            $this->writeProcess($poolConfig);
        }
    }

    private function writeProcess(PoolConfig $poolConfig)
    {
        $tlsDir = sprintf('/etc/openvpn/tls/%s', $poolConfig->v('instanceId'));

        $rangeIp = new IP($poolConfig->v('range'));
        $range6Ip = new IP($poolConfig->v('range6'));

        // static options
        $serverConfig = [
            '# OpenVPN Server Configuration',
            'verb 3',
            'dev-type tun',
            'user openvpn',
            'group openvpn',
            'topology subnet',
            'persist-key',
            'persist-tun',
            'keepalive 10 60',
            'comp-lzo no',
            'remote-cert-tls client',
            'tls-version-min 1.2',
            'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',
            'auth SHA256',
            'cipher AES-256-CBC',
            'client-connect /usr/bin/vpn-server-api-client-connect',
            'client-disconnect /usr/bin/vpn-server-api-client-disconnect',
            'push "comp-lzo no"',
            'push "explicit-exit-notify 3"',
            sprintf('ca %s/ca.crt', $tlsDir),
            sprintf('cert %s/server.crt', $tlsDir),
            sprintf('key %s/server.key', $tlsDir),
            sprintf('dh %s/dh.pem', $tlsDir),
            sprintf('tls-auth %s/ta.key 0', $tlsDir),
            sprintf('server %s %s', $rangeIp->getNetwork(), $rangeIp->getNetmask()),
            sprintf('server-ipv6 %s', $range6Ip->getAddressPrefix()),
            sprintf('max-clients %d', $rangeIp->getNumberOfHosts() - 1),
            sprintf('script-security %d', $poolConfig->v('twoFactor', false) ? 3 : 2),
            sprintf('dev %s', $poolConfig->v('dev')),
            sprintf('port %d', $poolConfig->v('port')),
            sprintf('management %s %d', $poolConfig->v('managementIp'), $poolConfig->v('managementPort')),
            sprintf('setenv INSTANCE_ID %s', $poolConfig->v('instanceId')),
            sprintf('setenv POOL_ID %s', $poolConfig->v('poolId')),
            sprintf('proto %s', 'tcp' === $poolConfig->v('proto') ? 'tcp-server' : 'udp'),
            sprintf('local %s', 'tcp' === $poolConfig->v('proto') ? $poolConfig->v('managementIp') : $poolConfig->v('listen', '0.0.0.0')),

            // increase the renegotiation time to 8h from the default of 1h when
            // using 2FA, otherwise the user would be asked for the 2FA key every
            // hour
            sprintf('reneg-sec %d', $poolConfig->v('twoFactor', false) ? 28800 : 3600),
        ];

        if ($poolConfig->v('enableLog', false)) {
            $serverConfig[] = 'log /dev/null';
        }

        if ('tcp' === $poolConfig->v('proto')) {
            $serverConfig[] = 'tcp-nodelay';
        }

        if ($poolConfig->v('twoFactor', false)) {
            $serverConfig[] = 'auth-user-pass-verify /usr/bin/vpn-server-api-verify-otp via-env';
        }

        // Routes
        $serverConfig = array_merge($serverConfig, self::getRoutes($poolConfig));

        // DNS
        $serverConfig = array_merge($serverConfig, self::getDns($poolConfig));

        // Client-to-client
        $serverConfig = array_merge($serverConfig, self::getClientToClient($poolConfig));

        sort($serverConfig, SORT_STRING);

        $configFile = sprintf('%s/%s', $this->vpnConfigDir, $poolConfig->v('configName'));

        if (false === @file_put_contents($configFile, implode(PHP_EOL, $serverConfig))) {
            throw new RuntimeException(sprintf('unable to write configuration file "%s"', $configFile));
        }
    }

    private static function getRoutes(PoolConfig $poolConfig)
    {
        $routeConfig = [];
        if ($poolConfig->v('defaultGateway', false)) {
            $routeConfig[] = 'push "redirect-gateway def1 bypass-dhcp"';

            // for Windows clients we need this extra route to mark the TAP adapter as
            // trusted and as having "Internet" access to allow the user to set it to
            // "Home" or "Work" to allow accessing file shares and printers
            // NOTE: this will break OS X tunnelblick because on disconnect it will
            // remove all default routes, including the one set before the VPN
            // was brought up
            //$routeConfig[] = 'push "route 0.0.0.0 0.0.0.0"';

            // for iOS we need this OpenVPN 2.4 "ipv6" flag to redirect-gateway
            // See https://docs.openvpn.net/docs/openvpn-connect/openvpn-connect-ios-faq.html
            $routeConfig[] = 'push "redirect-gateway ipv6"';

            // we use 2000::/3 instead of ::/0 because it seems to break on native IPv6
            // networks where the ::/0 default route already exists
            $routeConfig[] = 'push "route-ipv6 2000::/3"';
        } else {
            // there may be some routes specified, push those, and not the default
            foreach ($poolConfig->v('routes', []) as $route) {
                $routeIp = new IP($route);
                if (6 === $routeIp->getFamily()) {
                    // IPv6
                    $routeConfig[] = sprintf('push "route-ipv6 %s"', $routeIp->getAddressPrefix());
                } else {
                    // IPv4
                    $routeConfig[] = sprintf('push "route %s %s"', $routeIp->getAddress(), $routeIp->getNetmask());
                }
            }
        }

        return $routeConfig;
    }

    private static function getDns(PoolConfig $poolConfig)
    {
        // only push DNS if we are the default route
        if (!$poolConfig->v('defaultGateway', false)) {
            return [];
        }

        $dnsEntries = [];
        foreach ($poolConfig->v('dns', []) as $dnsAddress) {
            $dnsEntries[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress);
        }

        // prevent DNS leakage on Windows
        $dnsEntries[] = 'push "block-outside-dns"';

        return $dnsEntries;
    }

    private static function getClientToClient(PoolConfig $poolConfig)
    {
        if (!$poolConfig->v('clientToClient', false)) {
            return [];
        }

        $rangeIp = new IP($poolConfig->v('range'));
        $range6Ip = new IP($poolConfig->v('range6'));

        return [
            'client-to-client',
            sprintf('push "route %s %s"', $rangeIp->getAddress(), $rangeIp->getNetmask()),
            sprintf('push "route-ipv6 %s"', $range6Ip->getAddressPrefix()),
        ];
    }
}
