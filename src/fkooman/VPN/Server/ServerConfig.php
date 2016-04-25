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

use RuntimeException;

class ServerConfig
{
    public function get(array $serverConfig)
    {
        $requiredParameters = [
            'cn',
            'valid_from',
            'valid_to',
            'dev',          // tun-udp, tun-tcp, tun0, tun1, ...
            'proto',        // udp6, tcp-server
            'port',         // 1194, 443, ...
            'v4_prefix',    // 10.42.42.0/24, ...
            'v6_prefix',
            'dns',
            'management_port',  // 7505, 7506, ...
            'ca',
            'cert',
            'key',
            'dh',
            'ta',
            'listen',
            'otp',
        ];

        // XXX verify the parameters and types

        foreach ($requiredParameters as $p) {
            if (!array_key_exists($p, $serverConfig)) {
                throw new RuntimeException(sprintf('missing parameter "%s"', $p));
            }
        }

        $v4 = new IPv4($serverConfig['v4_prefix']);

        $dnsEntries = [];
        foreach ($serverConfig['dns'] as $dnsAddress) {
            $dnsEntries[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress);
        }

        $otpEntries = [];
        if ($serverConfig['otp']) {
            $otpEntries[] = 'auth-user-pass-verify /usr/bin/vpn-server-api-verify-otp via-env';

            # increase the renegotiation time to 8h from the default of 1h when
            # using OTP, otherwise the user will be asked for the OTP key every
            # hour
            $otpEntries[] = 'reneg-sec 28800';
        }

        $tcpOptions = [];
        if('tcp' === $serverConfig['proto'] || 'tcp6' === $serverConfig) {
            $tcpOptions[] = 'socket-flags TCP_NODELAY';
            $tcpOptions[] = 'push "socket-flags TCP_NODELAY"';
        }

        return [
            sprintf('# OpenVPN Server Configuration for %s', $serverConfig['cn']),

            sprintf('# Valid From: %s', date('c', $serverConfig['valid_from'])),
            sprintf('# Valid To: %s', date('c', $serverConfig['valid_to'])),

            sprintf('dev %s', $serverConfig['dev']),

            sprintf('local %s', $serverConfig['listen']),

            # UDP6 (works also for UDP)
            sprintf('proto %s', $serverConfig['proto']),
            sprintf('port %d', $serverConfig['port']),

            # IPv4
            sprintf('server %s %s', $v4->getNetwork(), $v4->getNetmask()),

            # IPv6
            sprintf('server-ipv6 %s', $serverConfig['v6_prefix']),

            'push "redirect-gateway def1 bypass-dhcp"',

            # for Windows clients we need this extra route to mark the TAP adapter as 
            # trusted and as having "Internet" access to allow the user to set it to 
            # "Home" or "Work" to allow accessing file shares and printers  
            #'push "route 0.0.0.0 0.0.0.0"',

            # for iOS we need this OpenVPN 2.4 "ipv6" flag to redirect-gateway
            # See https://docs.openvpn.net/docs/openvpn-connect/openvpn-connect-ios-faq.html
            'push "redirect-gateway ipv6"',

            # we use 2000::/3 instead of ::/0 because it seems to break on native IPv6 
            # networks where the ::/0 default route already exists
            'push "route-ipv6 2000::/3"',

            'topology subnet',
            # disable compression
            'comp-lzo no',
            'push "comp-lzo no"',
            'persist-key',
            'persist-tun',
            'verb 3',
            sprintf('max-clients %d', $v4->getNumberOfHosts() - 1),
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

            sprintf('script-security %d', $serverConfig['otp'] ? 3 : 2),
            'client-connect /usr/bin/vpn-server-api-client-connect',
            'client-disconnect /usr/bin/vpn-server-api-client-disconnect',

            # OTP
            implode(PHP_EOL, $otpEntries),

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

            # allow client-to-client communication, see openvpn(8)
            #client-to-client

            # need to allow 7505 also with SELinux
            sprintf('management localhost %d', $serverConfig['management_port']),

            sprintf('<ca>%s</ca>', PHP_EOL.$serverConfig['ca'].PHP_EOL),
            sprintf('<cert>%s</cert>', PHP_EOL.$serverConfig['cert'].PHP_EOL),
            sprintf('<key>%s</key>', PHP_EOL.$serverConfig['key'].PHP_EOL),
            sprintf('<dh>%s</dh>', PHP_EOL.$serverConfig['dh'].PHP_EOL),

            'key-direction 0',

            sprintf('<tls-auth>%s</tls-auth>', PHP_EOL.$serverConfig['ta'].PHP_EOL),
        ];
    }
}
