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
namespace fkooman\VPN\Server\OpenVpn;

require_once __DIR__.'/Test/TestSocket.php';

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\Dummy\DummyAuthentication;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\VPN\Server\OpenVpn\Test\TestSocket;

class OpenVpnModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $server;

    public function setUp()
    {
        $this->service = new Service();
        $dummyAuth = new DummyAuthentication('foo');
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register($dummyAuth, 'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetStatus()
    {
        $this->addModule('openvpn_23_status_one_client.txt');
        $this->assertSame(
            [
                'items' => [
                    [
                        'id' => 'one',
                        'ok' => true,
                        'status' => [
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
            $this->makeRequest('GET', '/status', [])
        );
    }

    public function testGetLoadStats()
    {
        $this->addModule('openvpn_23_load_stats.txt');
        $this->assertSame(
            [
                'items' => [
                    [
                        'id' => 'one',
                        'ok' => true,
                        'load-stats' => [
                            'number_of_clients' => 0,
                            'bytes_in' => 2224463,
                            'bytes_out' => 6102370,
                        ],
                    ],
                ],
            ],
            $this->makeRequest('GET', '/load-stats', [])
        );
    }

    public function testGetVersion()
    {
        $this->addModule('openvpn_23_version.txt');
        $this->assertSame(
            [
                'items' => [
                    [
                        'id' => 'one',
                        'ok' => true,
                        'version' => 'OpenVPN 2.3.8 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Aug  4 2015',
                    ],
                ],
            ],
            $this->makeRequest('GET', '/version', [])
        );
    }

    public function testKillClient()
    {
        $this->addModule('openvpn_23_kill_success.txt');
        $this->assertSame(
            [
                'items' => [
                    [
                        'id' => 'one',
                        'ok' => true,
                        'kill' => true,
                    ],
                ],
            ],
            $this->makeRequest('POST', '/kill', ['common_name' => 'xyz'])
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
                    ),
                    $queryBody
                )
            )->getBody();
        }
    }

    private static function readFile($fileName)
    {
        return @file_get_contents(
            sprintf(__DIR__.'/data/socket/%s', $fileName)
        );
    }

    private function addModule($fileName)
    {
        $serverManager = new ServerManager();
        $serverManager->addServer(
            new ServerApi(
                'one',
                new TestSocket(
                    self::readFile($fileName)
                )
            )
        );
        $this->service->addModule(
            new OpenVpnModule(
                $serverManager
            )
        );
    }
}
