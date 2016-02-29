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

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\Dummy\DummyAuthentication;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use Psr\Log\NullLogger;

class ConfigModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $service;

    public function setUp()
    {
        $tempDirName = tempnam(sys_get_temp_dir(), 'static');
        if (file_exists($tempDirName)) {
            @unlink($tempDirName);
        }
        @mkdir($tempDirName);

#        @file_put_contents($tempDirName.'/bar_foo', '{"pool": "default", "disable": false}');
        // a not disabled commonName
#        @file_put_contents($tempDirName.'/bar_baz', '{}');
#        // a disabled commonName
#        @file_put_contents($tempDirName.'/foo_baz', '{"disable": true}');

        $staticConfig = new StaticConfig($tempDirName);

#        $staticConfig->setConfig('foo_bar', ['disable' => false, 'pool' => 'default']);

        $configModule = new ConfigModule(
            $staticConfig,
            new NullLogger()
        );

        $this->service = new Service();
        $this->service->addModule($configModule);
        $dummyAuth = new DummyAuthentication('foo');
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register($dummyAuth, 'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetCnConfig()
    {
        $this->assertSame(
            [
                'pool' => 'default',
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
            $this->makeRequest('PUT', '/config/foo_bar', [], '{"pool": "admin"}')
        );
        $this->assertSame(
            [
                'pool' => 'admin',
                'disable' => false,
            ],
            $this->makeRequest('GET', '/config/foo_bar')
        );
    }

    public function testGetConfigs()
    {
        $this->assertSame(
            [
                'items' => [],
            ],
            $this->makeRequest('GET', '/config/')
        );
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/foo_bar', [], '{"pool": "admin"}')
        );
        $this->assertSame(
            [
                'items' => [
                    'foo_bar' => [
                        'pool' => 'admin',
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
                'items' => [],
            ],
            $this->makeRequest('GET', '/config/', ['user_id' => 'foo'])
        );
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/foo_bar', [], '{"pool": "admin"}')
        );
        $this->assertSame(
            [
                'items' => [
                    'foo_bar' => [
                        'pool' => 'admin',
                        'disable' => false,
                    ],
                ],
            ],
            $this->makeRequest('GET', '/config/', ['user_id' => 'foo'])
        );
        $this->assertSame(
            [
                'items' => [],
            ],
            $this->makeRequest('GET', '/config/', ['user_id' => 'bar'])
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
                    ),
                    null,
                    $body
                )
            )->getBody();
        }
    }
}
