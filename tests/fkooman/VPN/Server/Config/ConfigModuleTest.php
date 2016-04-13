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

require_once __DIR__.'/Test/TestConfigStorage.php';

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\VPN\Server\Config\Test\TestConfigStorage;
use Psr\Log\NullLogger;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;

class ConfigModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $service;

    public function setUp()
    {
        $configModule = new ConfigModule(
            new TestConfigStorage(),
            new NullLogger()
        );

        $this->service = new Service();
        $this->service->addModule($configModule);
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register(
            new BearerAuthentication(
                new ArrayBearerValidator(
                    [
                        'vpn-user-portal' => [
                            'token' => 'aabbcc',
                            'scope' => 'config_get config_update',
                        ],
                    ]
                )
            ),
            'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetCnConfig()
    {
        $this->assertSame(
            [
                'disable' => false,
            ],
            $this->makeRequest('GET', '/config/foo_bar')
        );
    }

    public function testPutCnConfig()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/foo_bar', [], '{"pool": "v6"}')
        );
    }

    public function testGetConfigs()
    {
        $this->assertSame(
            [
                'items' => [
                    'foo_bar' => [
                        'disable' => false,
                    ],
                    'bar_foo' => [
                        'disable' => true,
                    ],
                    'admin_xyz' => [
                        'disable' => false,
                    ],
                ],
            ],
            $this->makeRequest('GET', '/config/')
        );
    }

    public function testGetConfigsPerUser()
    {
        $this->assertSame(
            [
                'items' => [
                    'foo_bar' => [
                        'disable' => false,
                    ],
                ],
            ],
            $this->makeRequest('GET', '/config/', ['user_id' => 'foo'])
        );
    }

    private function makeRequest($requestMethod, $requestUri, array $queryBody = [], $body = '')
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
            // PUT
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
                    null,
                    $body
                )
            )->getBody();
        }
    }
}
