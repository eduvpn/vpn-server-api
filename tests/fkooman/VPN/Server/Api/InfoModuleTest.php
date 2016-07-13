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

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;
use fkooman\VPN\Server\Pools;

class InfoModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $service;

    public function setUp()
    {
        $infoModule = new InfoModule(
            new Pools(
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
            )
        );

        $this->service = new Service();
        $this->service->addModule($infoModule);
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register(
            new BearerAuthentication(
                new ArrayBearerValidator(
                    [
                        'vpn-user-portal' => [
                            'token' => 'portal',
                            'scope' => 'portal',
                        ],
                        'vpn-admin-portal' => [
                            'token' => 'admin',
                            'scope' => 'admin',
                        ],
                    ]
                )
            ),
            'api'
        );
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetServerInfoAsAdmin()
    {
        $this->assertSame(
            [
                'data' => array(
                'pools' => array(
                  0 => array(
                    'aclGroupList' => [],
                    'clientToClient' => false,
                    'defaultGateway' => false,
                    'dns' => array(
                      0 => '8.8.8.8',
                      1 => '2001:4860:4860::8888',
                    ),
                    'enableAcl' => false,
                    'enableLog' => false,
                    'extIf' => 'eth0',
                    'hostName' => 'vpn.example',
                    'id' => 'default',
                    'instances' => array(
                      0 => array(
                        'dev' => 'tun-default-0',
                        'managementPort' => 11940,
                        'port' => 1194,
                        'proto' => 'udp',
                        'range' => '10.42.42.0/26',
                        'range6' => 'fd00:4242:4242::/64',
                      ),
                      1 => array(
                        'dev' => 'tun-default-1',
                        'managementPort' => 11941,
                        'port' => 1195,
                        'proto' => 'udp',
                        'range' => '10.42.42.64/26',
                        'range6' => 'fd00:4242:4242:1::/64',
                      ),
                      2 => array(
                        'dev' => 'tun-default-2',
                        'managementPort' => 11942,
                        'port' => 1196,
                        'proto' => 'udp',
                        'range' => '10.42.42.128/26',
                        'range6' => 'fd00:4242:4242:2::/64',
                      ),
                      3 => array(
                        'dev' => 'tun-default-3',
                        'managementPort' => 11943,
                        'port' => 1194,
                        'proto' => 'tcp',
                        'range' => '10.42.42.192/26',
                        'range6' => 'fd00:4242:4242:3::/64',
                      ),
                    ),
                    'listen' => '::',
                    'managementIp' => '127.42.0.1',
                    'name' => 'Default Instance',
                    'range' => '10.42.42.0/24',
                    'range6' => 'fd00:4242:4242::/48',
                    'routes' => array(
                      0 => '192.168.1.0/24',
                      1 => 'fd00:1010:1010::/48',
                    ),
                    'twoFactor' => false,
                    'useNat' => false,
                  ),
                ),
                ),

            ],
            $this->makeRequest('/info/server', 'admin')
        );
    }

    public function testGetServerInfoAsPortal()
    {
        $this->assertSame(
            [
                'data' => array(
                'pools' => array(
                  0 => array(
                    'aclGroupList' => [],
                    'clientToClient' => false,
                    'defaultGateway' => false,
                    'dns' => array(
                      0 => '8.8.8.8',
                      1 => '2001:4860:4860::8888',
                    ),
                    'enableAcl' => false,
                    'enableLog' => false,
                    'extIf' => 'eth0',
                    'hostName' => 'vpn.example',
                    'id' => 'default',
                    'instances' => array(
                      0 => array(
                        'dev' => 'tun-default-0',
                        'managementPort' => 11940,
                        'port' => 1194,
                        'proto' => 'udp',
                        'range' => '10.42.42.0/26',
                        'range6' => 'fd00:4242:4242::/64',
                      ),
                      1 => array(
                        'dev' => 'tun-default-1',
                        'managementPort' => 11941,
                        'port' => 1195,
                        'proto' => 'udp',
                        'range' => '10.42.42.64/26',
                        'range6' => 'fd00:4242:4242:1::/64',
                      ),
                      2 => array(
                        'dev' => 'tun-default-2',
                        'managementPort' => 11942,
                        'port' => 1196,
                        'proto' => 'udp',
                        'range' => '10.42.42.128/26',
                        'range6' => 'fd00:4242:4242:2::/64',
                      ),
                      3 => array(
                        'dev' => 'tun-default-3',
                        'managementPort' => 11943,
                        'port' => 1194,
                        'proto' => 'tcp',
                        'range' => '10.42.42.192/26',
                        'range6' => 'fd00:4242:4242:3::/64',
                      ),
                    ),
                    'listen' => '::',
                    'managementIp' => '127.42.0.1',
                    'name' => 'Default Instance',
                    'range' => '10.42.42.0/24',
                    'range6' => 'fd00:4242:4242::/48',
                    'routes' => array(
                      0 => '192.168.1.0/24',
                      1 => 'fd00:1010:1010::/48',
                    ),
                    'twoFactor' => false,
                    'useNat' => false,
                  ),
                ),
                ),

            ],
            $this->makeRequest('/info/server', 'portal')
        );
    }

    private function makeRequest($requestUri, $accessToken)
    {
        return $this->service->run(
            new Request(
                array(
                    'SERVER_NAME' => 'www.example.org',
                    'SERVER_PORT' => 80,
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => $requestUri,
                    'PATH_INFO' => $requestUri,
                    'QUERY_STRING' => '',
                    'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken),
                )
            )
        )->getBody();
    }
}
