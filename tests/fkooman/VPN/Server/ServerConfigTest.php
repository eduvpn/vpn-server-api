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

class ServerConfigTest extends PHPUnit_Framework_TestCase
{
    public function testDefault()
    {
        $serverConfig = ServerConfig::getConfig(
            new Pools(
                [
                    'default' => [
                        'name' => 'Default Instance',
                        'hostName' => 'vpn.example',
                        'range' => '10.42.42.0/25',
                        'range6' => 'fd00:4242:4242::/48',
                        'extIf' => 'eth0',
                        'dns' => ['8.8.8.8', '2001:4860:4860::8888'],
                        'routes' => ['192.168.1.0/24', 'fd00:1010:1010::/48'],
                    ],
                ]
            )
        );

#        var_export($serverConfig);

        $this->assertSame(
            [
                'default-0' => array(
                    0 => '# OpenVPN Server Configuration',
                    1 => 'auth SHA256',
                    2 => 'ca /etc/openvpn/tls/ca.crt',
                    3 => 'cert /etc/openvpn/tls/server.crt',
                    4 => 'cipher AES-256-CBC',
                    5 => 'client-connect /usr/bin/vpn-server-api-client-connect',
                    6 => 'client-disconnect /usr/bin/vpn-server-api-client-disconnect',
                    7 => 'comp-lzo no',
                    8 => 'crl-verify /var/lib/vpn-server-api/ca.crl',
                    9 => 'dev tun-default-0',
                    10 => 'dh /etc/openvpn/tls/dh.pem',
                    11 => 'group openvpn',
                    12 => 'keepalive 10 60',
                    13 => 'key /etc/openvpn/tls/server.key',
                    14 => 'local ::',
                    15 => 'management 127.42.0.1 11940',
                    16 => 'max-clients 61',
                    17 => 'persist-key',
                    18 => 'persist-tun',
                    19 => 'port 1194',
                    20 => 'proto udp6',
                    21 => 'push "comp-lzo no"',
                    22 => 'push "explicit-exit-notify 3"',
                    23 => 'push "route 192.168.1.0 255.255.255.0"',
                    24 => 'push "route-ipv6 fd00:1010:1010::/48"',
                    25 => 'remote-cert-tls client',
                    26 => 'reneg-sec 3600',
                    27 => 'script-security 2',
                    28 => 'server 10.42.42.0 255.255.255.192',
                    29 => 'server-ipv6 fd00:4242:4242::/64',
                    30 => 'tls-auth /etc/openvpn/tls/ta.key 0',
                    31 => 'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',
                    32 => 'tls-version-min 1.2',
                    33 => 'topology subnet',
                    34 => 'user openvpn',
                    35 => 'verb 3',
                ),
                'default-1' => array(
                    0 => '# OpenVPN Server Configuration',
                    1 => 'auth SHA256',
                    2 => 'ca /etc/openvpn/tls/ca.crt',
                    3 => 'cert /etc/openvpn/tls/server.crt',
                    4 => 'cipher AES-256-CBC',
                    5 => 'client-connect /usr/bin/vpn-server-api-client-connect',
                    6 => 'client-disconnect /usr/bin/vpn-server-api-client-disconnect',
                    7 => 'comp-lzo no',
                    8 => 'crl-verify /var/lib/vpn-server-api/ca.crl',
                    9 => 'dev tun-default-1',
                    10 => 'dh /etc/openvpn/tls/dh.pem',
                    11 => 'group openvpn',
                    12 => 'keepalive 10 60',
                    13 => 'key /etc/openvpn/tls/server.key',
                    14 => 'local 127.42.0.1',
                    15 => 'management 127.42.0.1 11941',
                    16 => 'max-clients 61',
                    17 => 'persist-key',
                    18 => 'persist-tun',
                    19 => 'port 1194',
                    20 => 'proto tcp-server',
                    21 => 'push "comp-lzo no"',
                    22 => 'push "explicit-exit-notify 3"',
                    23 => 'push "route 192.168.1.0 255.255.255.0"',
                    24 => 'push "route-ipv6 fd00:1010:1010::/48"',
                    25 => 'push "socket-flags TCP_NODELAY"',
                    26 => 'remote-cert-tls client',
                    27 => 'reneg-sec 3600',
                    28 => 'script-security 2',
                    29 => 'server 10.42.42.64 255.255.255.192',
                    30 => 'server-ipv6 fd00:4242:4242:1::/64',
                    31 => 'socket-flags TCP_NODELAY',
                    32 => 'tls-auth /etc/openvpn/tls/ta.key 0',
                    33 => 'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',
                    34 => 'tls-version-min 1.2',
                    35 => 'topology subnet',
                    36 => 'user openvpn',
                    37 => 'verb 3',
                ),
            ],
            $serverConfig
        );
    }
}
