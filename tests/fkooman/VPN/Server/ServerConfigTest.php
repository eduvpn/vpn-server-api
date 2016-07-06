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
                    '# OpenVPN Server Configuration',
                    'auth SHA256',
                    'ca /etc/openvpn/tls/ca.crt',
                    'cert /etc/openvpn/tls/server.crt',
                    'cipher AES-256-CBC',
                    'client-connect /usr/bin/vpn-server-api-client-connect',
                    'client-disconnect /usr/bin/vpn-server-api-client-disconnect',
                    'comp-lzo no',
                    'dev tun-default-0',
                    'dh /etc/openvpn/tls/dh.pem',
                    'group openvpn',
                    'keepalive 10 60',
                    'key /etc/openvpn/tls/server.key',
                    'local ::',
                    'log /dev/null',
                    'management 127.42.0.1 11940',
                    'max-clients 61',
                    'persist-key',
                    'persist-tun',
                    'port 1194',
                    'proto udp6',
                    'push "comp-lzo no"',
                    'push "explicit-exit-notify 3"',
                    'push "route 192.168.1.0 255.255.255.0"',
                    'push "route-ipv6 fd00:1010:1010::/48"',
                    'remote-cert-tls client',
                    'reneg-sec 3600',
                    'script-security 2',
                    'server 10.42.42.0 255.255.255.192',
                    'server-ipv6 fd00:4242:4242::/64',
                    'setenv POOL_ID default',
                    'tls-auth /etc/openvpn/tls/ta.key 0',
                    'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',
                    'tls-version-min 1.2',
                    'topology subnet',
                    'user openvpn',
                    'verb 3',
                ),
                'default-1' => array(
                    '# OpenVPN Server Configuration',
                    'auth SHA256',
                    'ca /etc/openvpn/tls/ca.crt',
                    'cert /etc/openvpn/tls/server.crt',
                    'cipher AES-256-CBC',
                    'client-connect /usr/bin/vpn-server-api-client-connect',
                    'client-disconnect /usr/bin/vpn-server-api-client-disconnect',
                    'comp-lzo no',
                    'dev tun-default-1',
                    'dh /etc/openvpn/tls/dh.pem',
                    'group openvpn',
                    'keepalive 10 60',
                    'key /etc/openvpn/tls/server.key',
                    'local 127.42.0.1',
                    'log /dev/null',
                    'management 127.42.0.1 11941',
                    'max-clients 61',
                    'persist-key',
                    'persist-tun',
                    'port 1194',
                    'proto tcp-server',
                    'push "comp-lzo no"',
                    'push "explicit-exit-notify 3"',
                    'push "route 192.168.1.0 255.255.255.0"',
                    'push "route-ipv6 fd00:1010:1010::/48"',
                    'remote-cert-tls client',
                    'reneg-sec 3600',
                    'script-security 2',
                    'server 10.42.42.64 255.255.255.192',
                    'server-ipv6 fd00:4242:4242:1::/64',
                    'setenv POOL_ID default',
                    'tcp-nodelay',
                    'tls-auth /etc/openvpn/tls/ta.key 0',
                    'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',
                    'tls-version-min 1.2',
                    'topology subnet',
                    'user openvpn',
                    'verb 3',
                ),
            ],
            $serverConfig
        );
    }
}
