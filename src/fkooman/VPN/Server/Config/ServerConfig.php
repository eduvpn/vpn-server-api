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

namespace fkooman\VPN\Server\Config;

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
            'port',         // 1194
            'v4_network',
            'v4_netmask',
            'v6_network',
            'v6_gateway',
            'management_port',  // 7505, 7506, ...
            'ca',
            'cert',
            'key',
            'dh',
            'ta',
        ];

        // XXX verfiy the parameters and types

        foreach ($requiredParameters as $p) {
            if (!array_key_exists($p, $serverConfig)) {
                throw new RuntimeException(sprintf('missing parameter "%s"', $p));
            }
        }

        return [
            sprintf('# OpenVPN Server Configuration for %s', $serverConfig['cn']),

            sprintf('# Valid From: %s', date('c', $serverConfig['valid_from'])),
            sprintf('# Valid To: %s', date('c', $serverConfig['valid_to'])),

            sprintf('dev %s', $serverConfig['dev']),

            # UDP6 (works also for UDP)
            sprintf('proto %s', $serverConfig['proto']),
            sprintf('port %d', $serverConfig['port']),

            # IPv4
            sprintf('server %s %s nopool', $serverConfig['v4_network'], $serverConfig['v4_netmask']),

            # IPv6
            sprintf('ifconfig-ipv6 %s %s', $serverConfig['v6_network'], $serverConfig['v6_gateway']),
            'tun-ipv6',
            'push "tun-ipv6"',

            'topology subnet',
            # disable compression
            'comp-lzo no',
            'push "comp-lzo no"',
            'persist-key',
            'persist-tun',
            'verb 3',
            'max-clients 100',
            'keepalive 10 60',
            'user openvpn',
            'group openvpn',
            'remote-cert-tls client',

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
            'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS_DHE_RSA_WITH_AES_256_CBC_SHA',

            'script-security 2',
            'client-connect /usr/bin/vpn-server-api-client-connect',
            'client-disconnect /usr/bin/vpn-server-api-client-disconnect',

            # Certificate Revocation List
            'crl-verify /var/lib/vpn-server-api/ca.crl',

            # ask client to tell us on disconnect
            'push "explicit-exit-notify 3"',

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
