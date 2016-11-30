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

namespace SURFnet\VPN\Server\Api;

use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Acl\Provider\StaticProvider;
use SURFnet\VPN\Server\Storage;

class ConnectionsModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $random = $this->getMockBuilder('SURFnet\VPN\Common\RandomInterface')->getMock();
        $random->method('get')->will($this->onConsecutiveCalls('random_1', 'random_2'));

        $storage = new Storage(
            new PDO('sqlite::memory:'),
            $random
        );
        $storage->init();
        $storage->addCertificate('foo', 'abcd1234', 'ABCD1234', 12345678, 23456789);
        $storage->clientConnect('internet', 'abcd1234', '10.10.10.10', 'fd00:4242:4242:4242::', 12345678);

        $config = Config::fromFile(sprintf('%s/data/config.yaml', __DIR__));

        $groupProviders = [
            new StaticProvider(
                new Config(
                    $config->v('groupProviders', 'StaticProvider')
                )
            ),
        ];

        $this->service = new Service();
        $this->service->addModule(
            new ConnectionsModule(
                $config,
                $storage,
                $groupProviders
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-server-node' => 'aabbcc',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testConnect()
    {
        $this->assertSame(
            [
                'data' => [
                    'connect' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                '/connect',
                [],
                [
                    'profile_id' => 'internet',
                    'common_name' => 'abcd1234',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                ]
            )
        );
    }

    public function testConnectInAcl()
    {
        $this->assertSame(
            [
                'data' => [
                    'connect' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                '/connect',
                [],
                [
                    'profile_id' => 'acl',
                    'common_name' => 'abcd1234',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                ]
            )
        );
    }

    public function testConnectNotInAcl()
    {
        $this->assertSame(
            [
                'data' => [
                    'connect' => [
                        'ok' => false,
                        'error' => 'user not in ACL',
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                '/connect',
                [],
                [
                    'profile_id' => 'acl2',
                    'common_name' => 'abcd1234',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                ]
            )
        );
    }

    public function testDisconnect()
    {
        $this->assertSame(
            [
                'data' => [
                    'disconnect' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                '/disconnect',
                [],
                [
                    'profile_id' => 'internet',
                    'common_name' => 'abcd1234',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                    'disconnected_at' => 23456789,
                    'bytes_transferred' => 2222222,
                ]
            )
        );
    }

    private function makeRequest(array $basicAuth, $requestMethod, $pathInfo, array $getData = [], array $postData = [])
    {
        $response = $this->service->run(
            new Request(
                [
                    'SERVER_PORT' => 80,
                    'SERVER_NAME' => 'vpn.example',
                    'REQUEST_METHOD' => $requestMethod,
                    'PATH_INFO' => $pathInfo,
                    'REQUEST_URI' => $pathInfo,
                    'PHP_AUTH_USER' => $basicAuth[0],
                    'PHP_AUTH_PW' => $basicAuth[1],
                ],
                $getData,
                $postData
            )
        );

        return json_decode($response->getBody(), true);
    }
}
