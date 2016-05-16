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

class ServerConfig
{
    public static function get(Pool $pool, Instance $instance)
    {
        $routeConfig = [];
        if ($pool->getDefaultGateway()) {
            $routeConfig[] = 'push "redirect-gateway def1 bypass-dhcp"';

            # for Windows clients we need this extra route to mark the TAP adapter as 
            # trusted and as having "Internet" access to allow the user to set it to 
            # "Home" or "Work" to allow accessing file shares and printers  
            #$routeConfig[] = 'push "route 0.0.0.0 0.0.0.0"';

            # for iOS we need this OpenVPN 2.4 "ipv6" flag to redirect-gateway
            # See https://docs.openvpn.net/docs/openvpn-connect/openvpn-connect-ios-faq.html
            $routeConfig[] = 'push "redirect-gateway ipv6"';

            # we use 2000::/3 instead of ::/0 because it seems to break on native IPv6 
            # networks where the ::/0 default route already exists
            $routeConfig[] = 'push "route-ipv6 2000::/3"';
        } else {
            // there are some routes specified, push those, and not the default 
            foreach ($pool->getRoutes() as $route) {
                if (6 === $route->getFamily()) {
                    // IPv6
                    $routeConfig[] = sprintf('push "route-ipv6 %s"', $route->getAddressPrefix());
                } else {
                    // IPv4
                    $routeConfig[] = sprintf('push "route %s"', $route->getAddressPrefix());
                }
            }
        }

        $dnsEntries = [];
        if ($pool->getDefaultGateway()) {
            // only push DNS when we are the default route
            foreach ($pool->getDns() as $dnsAddress) {
                $dnsEntries[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress->getAddress());
            }
        }

        $tfaEntries = [];
        if ($pool->getTwoFactor()) {
            $tfaEntries[] = 'auth-user-pass-verify /usr/bin/vpn-server-api-verify-otp via-env';
        }

        $tcpOptions = [];
        if ('tcp-server' === $instance->getProto()) {
            $tcpOptions[] = 'socket-flags TCP_NODELAY';
            $tcpOptions[] = 'push "socket-flags TCP_NODELAY"';
        }

        $clientToClient = [];
        if ($pool->getClientToClient()) {
            $clientToClient[] = 'client-to-client';
        }

        if ('tcp-server' === $instance->getProto()) {
            // must listen on managementIp
            $listen = $pool->getManagementIp();
        } else {
            $listen = $pool->getListen();
        }

        return [
            '# OpenVPN Server Configuration',

            sprintf('dev %s', $instance->getDev()),

            sprintf('local %s', $listen->getAddress()),

            # UDP6 (works also for UDP)
            sprintf('proto %s', $instance->getProto()),
            sprintf('port %d', $instance->getPort()),

            # IPv4
            sprintf('server %s %s', $instance->getRange()->getNetwork(), $instance->getRange()->getNetmask()),

            # IPv6
            sprintf('server-ipv6 %s', $instance->getRange6()),

            implode(PHP_EOL, $routeConfig),

            implode(PHP_EOL, $clientToClient),

            'topology subnet',
            # disable compression
            'comp-lzo no',
            'push "comp-lzo no"',
            'persist-key',
            'persist-tun',
            'verb 3',
            sprintf('max-clients %d', $instance->getRange()->getNumberOfHosts() - 1),
            'keepalive 10 60',
            'user openvpn',
            'group openvpn',
            'remote-cert-tls client',

            # when using TCP, we want to reduce the latency of the TCP tunnel
            implode(PHP_EOL, $tcpOptions),

            # CRYPTO (DATA CHANNEL)
            'auth SHA256',
            'cipher AES-256-CBC',

            # CRYPTO (CONTROL CHANNEL)
            # @see RFC 7525  
            # @see https://bettercrypto.org
            # @see https://community.openvpn.net/openvpn/wiki/Hardening
            'tls-version-min 1.2',

            # To work with default configuration in iOS OpenVPN with
            # "Force AES-CBC ciphersuites" enabled, we need to accept an 
            # additional cipher "TLS_DHE_RSA_WITH_AES_256_CBC_SHA"
            'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',

            sprintf('script-security %d', $pool->getTwoFactor() ? 3 : 2),
            'client-connect /usr/bin/vpn-server-api-client-connect',
            'client-disconnect /usr/bin/vpn-server-api-client-disconnect',

            # increase the renegotiation time to 8h from the default of 1h when
            # using 2FA, otherwise the user will be asked for the 2FA key every
            # hour
            sprintf('reneg-sec %d', $pool->getTwoFactor() ? 28800 : 3600),

            # 2FA
            implode(PHP_EOL, $tfaEntries),

            # Certificate Revocation List
            'crl-verify /var/lib/vpn-server-api/ca.crl',

            # ask client to tell us on disconnect
            'push "explicit-exit-notify 3"',

            # DNS
            implode(PHP_EOL, $dnsEntries),

            # disable "netbios", i.e. Windows file sharing over TCP/IP
            #push "dhcp-option DISABLE-NBT"

            # also send a NTP server
            #push "dhcp-option NTP time.example.org"

            sprintf('management %s %d', $pool->getManagementIp()->getAddress(), $instance->getManagementPort()),

            'ca /etc/openvpn/ca.crt',
            'cert /etc/openvpn/server.crt',
            'key /etc/openvpn/server.key',
            'dh /etc/openvpn/dh.pem',
            'tls-auth /etc/openvpn/ta.key 0',
        ];
    }
}
