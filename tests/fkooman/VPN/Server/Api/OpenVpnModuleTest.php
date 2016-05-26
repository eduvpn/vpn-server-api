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

namespace fkooman\VPN\Server\Api;

require_once __DIR__.'/Test/TestSocket.php';

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\VPN\Server\OpenVpn\Test\TestSocket;
use Psr\Log\NullLogger;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;
use fkooman\VPN\Server\Pools;
use fkooman\VPN\Server\OpenVpn\ServerManager;

class OpenVpnModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $server;

    public function setUp()
    {
        $this->service = new Service();
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register(
            new BearerAuthentication(
                new ArrayBearerValidator(
                    [
                        'vpn-user-portal' => [
                            'token' => 'aabbcc',
                            'scope' => 'admin portal',
                        ],
                    ]
                )
            ),
            'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetStatus()
    {
        $this->addModule('connections');
        $this->assertSame(
            [
                'data' => [
                    'connections' => [
                        [
                            'id' => 'default',
                            'connections' => [
                                [
                                    'common_name' => 'fkooman_samsung_i9300',
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
                ],
            ],
            $this->makeRequest('GET', '/openvpn/connections', [])
        );
    }

    public function testKillClient()
    {
        $this->addModule('kill');
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('POST', '/openvpn/kill', ['common_name' => 'xyz'])
        );
    }

    private function makeRequest($requestMethod, $requestUri, array $queryBody = [])
    {
        if ('GET' === $requestMethod || 'DELETE' === $requestMethod) {
            return $this->service->run(
                new Request(
                    array(
                        'SERVER_NAME' => 'www.example.org',
                        'SERVER_PORT' => 80,
                        'REQUEST_METHOD' => $requestMethod,
                        'REQUEST_URI' => sprintf('%s?%s', $requestUri, http_build_query($queryBody)),
                        'PATH_INFO' => $requestUri,
                        'QUERY_STRING' => http_build_query($queryBody),
                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', 'aabbcc'),
                    )
                )
            )->getBody();
        } else {
            // POST
            return $this->service->run(
                new Request(
                    array(
                        'SERVER_NAME' => 'www.example.org',
                        'SERVER_PORT' => 80,
                        'REQUEST_METHOD' => $requestMethod,
                        'REQUEST_URI' => $requestUri,
                        'PATH_INFO' => $requestUri,
                        'QUERY_STRING' => '',
                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', 'aabbcc'),
                    ),
                    $queryBody
                )
            )->getBody();
        }
    }

    private function addModule($callType)
    {
        $p = new Pools(
            [
                'default' => [
                    'name' => 'Default Instance',
                    'hostName' => 'vpn.example',
                    'extIf' => 'eth0',
                    'range' => '10.42.42.0/24',
                    'range6' => 'fd00:4242:4242::/48',
                    'dns' => ['8.8.8.8', '2001:4860:4860::8888'],
                    'routes' => ['192.168.1.0/24', 'fd00:1010:1010::/48'],
                ],
            ]
        );

        $socket = new TestSocket($callType);
        $serverManager = new ServerManager($p, $socket, new NullLogger());

        $this->service->addModule(
            new OpenVpnModule(
                $serverManager
            )
        );
    }
}
