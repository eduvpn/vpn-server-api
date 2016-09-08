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
namespace SURFnet\VPN\Server\OpenVpn;

require_once sprintf('%s/Test/TestSocket.php', __DIR__);

use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Server\OpenVpn\Test\TestSocket;
use Psr\Log\NullLogger;
use SURFnet\VPN\Server\InstanceConfig;

class ServerManagerTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
    }

    public function testGetStatus()
    {
        $serverManager = new ServerManager(
            new InstanceConfig(
                [
                    'instanceNumber' => 1,
                    'vpnPools' => [
                        'default' => [
                            'name' => 'Default Instance',
                            'hostName' => 'vpn.example',
                            'extIf' => 'eth0',
                            'range' => '10.42.42.0/24',
                            'range6' => 'fd00:4242:4242::/48',
                            'dns' => ['8.8.8.8', '2001:4860:4860::8888'],
                            'routes' => ['192.168.1.0/24', 'fd00:1010:1010::/48'],
                        ],
                    ],
                ]
            ),
            new TestSocket('connections'),
            new NullLogger()
        );

        $this->assertSame(
            [
                [
                    'id' => 'default',
                    'connections' => [
                        [
                            'common_name' => 'fkooman_samsung_i9300',
                            'user_id' => 'fkooman',
                            'name' => 'samsung_i9300',
                            'real_address' => '91.64.87.183:43103',
                            'bytes_in' => 18301,
                            'bytes_out' => 30009,
                            'connected_since' => 1451323167,
                            'virtual_address' => [
                                'fd00:4242:4242::1003',
                                '10.42.42.5',
                            ],
                        ],
                    ],
                ],
            ],
            $serverManager->connections()
        );
    }

//    public function testKillClient()
//    {
//        $this->addModule('kill');
//        $this->assertSame(
//            [
//                'data' => [
//                    'ok' => true,
//                ],
//            ],
//            $this->makeRequest('POST', '/openvpn/kill', ['common_name' => 'xyz'])
//        );
//    }

//    private function makeRequest($requestMethod, $requestUri, array $queryBody = [])
//    {
//        if ('GET' === $requestMethod || 'DELETE' === $requestMethod) {
//            return $this->service->run(
//                new Request(
//                    array(
//                        'SERVER_NAME' => 'www.example.org',
//                        'SERVER_PORT' => 80,
//                        'REQUEST_METHOD' => $requestMethod,
//                        'REQUEST_URI' => sprintf('%s?%s', $requestUri, http_build_query($queryBody)),
//                        'PATH_INFO' => $requestUri,
//                        'QUERY_STRING' => http_build_query($queryBody),
//                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', 'aabbcc'),
//                    )
//                )
//            )->getBody();
//        } else {
//            // POST
//            return $this->service->run(
//                new Request(
//                    array(
//                        'SERVER_NAME' => 'www.example.org',
//                        'SERVER_PORT' => 80,
//                        'REQUEST_METHOD' => $requestMethod,
//                        'REQUEST_URI' => $requestUri,
//                        'PATH_INFO' => $requestUri,
//                        'QUERY_STRING' => '',
//                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', 'aabbcc'),
//                    ),
//                    $queryBody
//                )
//            )->getBody();
//        }
//    }

//    private function addModule($callType)
//    {
//        $p = new Pools(
//            [
//                'default' => [
//                    'name' => 'Default Instance',
//                    'hostName' => 'vpn.example',
//                    'extIf' => 'eth0',
//                    'range' => '10.42.42.0/24',
//                    'range6' => 'fd00:4242:4242::/48',
//                    'dns' => ['8.8.8.8', '2001:4860:4860::8888'],
//                    'routes' => ['192.168.1.0/24', 'fd00:1010:1010::/48'],
//                ],
//            ]
//        );

//        $socket = new TestSocket($callType);
//        $serverManager = new ServerManager($p, $socket, new NullLogger());

//        $this->service->addModule(
//            new OpenVpnModule(
//                $serverManager
//            )
//        );
//    }
}
