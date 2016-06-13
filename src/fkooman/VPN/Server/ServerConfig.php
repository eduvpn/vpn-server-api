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

class ServerConfig
{
    public static function getConfig(Pools $pools)
    {
        $allConfig = [];

        foreach ($pools as $pool) {
            foreach ($pool->getInstances() as $i => $instance) {
                // static options
                $serverConfig = [
                    '# OpenVPN Server Configuration',
                    'verb 3',
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
                    'ca /etc/openvpn/tls/ca.crt',
                    'cert /etc/openvpn/tls/server.crt',
                    'key /etc/openvpn/tls/server.key',
                    'dh /etc/openvpn/tls/dh.pem',
                    'tls-auth /etc/openvpn/tls/ta.key 0',
                    'crl-verify /var/lib/vpn-server-api/ca.crl',
                    'client-connect /usr/bin/vpn-server-api-client-connect',
                    'client-disconnect /usr/bin/vpn-server-api-client-disconnect',
                    'push "comp-lzo no"',
                    'push "explicit-exit-notify 3"',
                ];

                // Routes
                $serverConfig = array_merge($serverConfig, self::getRoutes($pool));

                // DNS
                $serverConfig = array_merge($serverConfig, self::getDns($pool));

                // Client-to-client
                $serverConfig = array_merge($serverConfig, self::getClientToClient($pool));

                // OTP
                $serverConfig = array_merge($serverConfig, self::getOtp($pool));

                // IP configuration
                $serverConfig[] = sprintf('server %s %s', $instance->getRange()->getNetwork(), $instance->getRange()->getNetmask());
                $serverConfig[] = sprintf('server-ipv6 %s', $instance->getRange6());
                $serverConfig[] = sprintf('max-clients %d', $instance->getRange()->getNumberOfHosts() - 1);

                // TCP options
                $serverConfig = array_merge($serverConfig, self::getTcpOptions($instance));

                // Script Security
                $serverConfig[] = sprintf('script-security %d', $pool->getTwoFactor() ? 3 : 2);

                # increase the renegotiation time to 8h from the default of 1h when
                # using 2FA, otherwise the user will be asked for the 2FA key every
                # hour
                $serverConfig[] = sprintf('reneg-sec %d', $pool->getTwoFactor() ? 28800 : 3600);

                // Management
                $serverConfig[] = sprintf('management %s %d', $pool->getManagementIp()->getAddress(), $instance->getManagementPort());

                // Listen
                $serverConfig = array_merge($serverConfig, self::getListen($pool, $instance));

                // Dev
                $serverConfig[] = sprintf('dev %s', $instance->getDev());

                // Proto
                $serverConfig = array_merge($serverConfig, self::getProto($pool, $instance));

                // Port
                $serverConfig[] = sprintf('port %d', $instance->getPort());

                // Log
                $serverConfig = array_merge($serverConfig, self::getLog($pool));

                // Pool ID
                $serverConfig[] = sprintf('setenv POOL_ID %s', $pool->getId());

                // Fix MTU
                $serverConfig = array_merge($serverConfig, self::getFixMtu($pool, $instance));

                sort($serverConfig, SORT_STRING);

                $allConfig[sprintf('%s-%d', $pool->getId(), $i)] = $serverConfig;
            }
        }

        return $allConfig;
    }

    private static function getRoutes(Pool $pool)
    {
        $routeConfig = [];
        if ($pool->getDefaultGateway()) {
            $routeConfig[] = 'push "redirect-gateway def1 bypass-dhcp"';

            # for Windows clients we need this extra route to mark the TAP adapter as
            # trusted and as having "Internet" access to allow the user to set it to
            # "Home" or "Work" to allow accessing file shares and printers
            # NOTE: this will break OS X tunnelblick because on disconnect it will
            # remove all default routes, including the one set before the VPN
            # was brought up
            #$routeConfig[] = 'push "route 0.0.0.0 0.0.0.0"';

            # for iOS we need this OpenVPN 2.4 "ipv6" flag to redirect-gateway
            # See https://docs.openvpn.net/docs/openvpn-connect/openvpn-connect-ios-faq.html
            $routeConfig[] = 'push "redirect-gateway ipv6"';

            # we use 2000::/3 instead of ::/0 because it seems to break on native IPv6
            # networks where the ::/0 default route already exists
            $routeConfig[] = 'push "route-ipv6 2000::/3"';
        } else {
            // there may be some routes specified, push those, and not the default
            foreach ($pool->getRoutes() as $route) {
                if (6 === $route->getFamily()) {
                    // IPv6
                    $routeConfig[] = sprintf('push "route-ipv6 %s"', $route->getAddressPrefix());
                } else {
                    // IPv4
                    $routeConfig[] = sprintf('push "route %s %s"', $route->getAddress(), $route->getNetmask());
                }
            }
        }

        return $routeConfig;
    }

    private static function getDns(Pool $pool)
    {
        // only push DNS if we are the default route
        if (!$pool->getDefaultGateway()) {
            return [];
        }

        $dnsEntries = [];
        foreach ($pool->getDns() as $dnsAddress) {
            $dnsEntries[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress->getAddress());
        }

        # prevent DNS leakage on Windows
        $dnsEntries[] = 'push "block-outside-dns"';

        return $dnsEntries;
    }

    private static function getOtp(Pool $pool)
    {
        if (!$pool->getTwoFactor()) {
            return [];
        }

        return ['auth-user-pass-verify /usr/bin/vpn-server-api-verify-otp via-env'];
    }

    private static function getLog(Pool $pool)
    {
        if ($pool->getEnableLog()) {
            return [];
        }

        return ['log /dev/null'];
    }

    private static function getClientToClient(Pool $pool)
    {
        if (!$pool->getClientToClient()) {
            return [];
        }

        return [
            'client-to-client',
            sprintf('push "route %s %s"', $pool->getRange()->getAddress(), $pool->getRange()->getNetmask()),
            sprintf('push "route-ipv6 %s"', $pool->getRange6()->getAddressPrefix()),
        ];
    }

    private static function getTcpOptions(Instance $instance)
    {
        if ('tcp' !== $instance->getProto()) {
            return [];
        }

        return [
            'tcp-nodelay',
        ];
    }

    private static function getListen(Pool $pool, Instance $instance)
    {
        // TCP instance always listens on management IP as sniproxy
        // will redirect traffic there
        if ('tcp' === $instance->getProto()) {
            return [
                sprintf('local %s', $pool->getManagementIp()->getAddress()),
            ];
        }

        return [
            sprintf('local %s', $pool->getListen()->getAddress()),
        ];
    }

    private static function getProto(Pool $pool, Instance $instance)
    {
        if ('tcp' === $instance->getProto()) {
            // tcp
            if (4 === $pool->getListen()->getFamily() || '::' === $pool->getListen()->getAddress()) {
                // this is the default, so we listen on IPv4
                $proto = 'tcp-server';
            } else {
                $proto = 'tcp6-server';
            }
        } else {
            // udp
            if (6 === $pool->getListen()->getFamily()) {
                $proto = 'udp6';
            } else {
                $proto = 'udp';
            }
        }

        return [
            sprintf('proto %s', $proto),
        ];
    }

    private static function getFixMtu(Pool $pool, Instance $instance)
    {
        if (!$pool->getFixMtu() || 'tcp' === $instance->getProto()) {
            return [];
        }

        return [
            'tun-mtu 1500',
            'fragment 1300',
            'mssfix',
            'push "tun-mtu 1500"',
            'push "fragment 1300"',
            'push "mssfix"',
        ];
    }
}
