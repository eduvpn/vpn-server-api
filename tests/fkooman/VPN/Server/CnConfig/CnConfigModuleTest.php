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

namespace fkooman\VPN\Server\CnConfig;

require_once __DIR__.'/Test/TestIO.php';

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\VPN\Server\CnConfig\Test\TestIO;
use Psr\Log\NullLogger;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;
use fkooman\Json\Json;

class CnConfigModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $service;

    public function setUp()
    {
        $configModule = new CnConfigModule(
            '/tmp',
            new NullLogger(),
            new TestIO()
        );

        $this->service = new Service();
        $this->service->addModule($configModule);
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
            'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetCns()
    {
        $this->assertSame(
            [
                'foo_one' => [
                    'disable' => true,
                ],
                'bar_one' => [
                    'disable' => true,
                ],

            ],
            $this->makeRequest('GET', '/config/common_names', [], 'admin')
        );
    }

    public function testGetCnsForUser()
    {
        $this->assertSame(
            [
                'foo_one' => [
                    'disable' => true,
                ],
            ],
            $this->makeRequest('GET', '/config/common_names', ['user_id' => 'foo'], 'admin')
        );
    }

    public function testGetNonExistingCn()
    {
        $this->assertSame(
            [
                'disable' => false,
            ],
            $this->makeRequest('GET', '/config/common_names/foo_not-there', [], 'admin')
        );
    }

    public function testGetExistingCn()
    {
        $this->assertSame(
            [
                'disable' => true,
            ],
            $this->makeRequest('GET', '/config/common_names/foo_one', [], 'admin')
        );
    }

    public function testPutCnConfig()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/common_names/foo_two', ['disable' => true], 'admin')
        );
        $this->assertSame(
            [
                'disable' => true,
            ],
            $this->makeRequest('GET', '/config/common_names/foo_two', [], 'admin')
        );
    }

    private function makeRequest($requestMethod, $requestUri, array $queryBody = [], $accessToken)
    {
        if ('GET' === $requestMethod) {
            // GET
            return $this->service->run(
                new Request(
                    array(
                        'SERVER_NAME' => 'www.example.org',
                        'SERVER_PORT' => 80,
                        'REQUEST_METHOD' => $requestMethod,
                        'REQUEST_URI' => sprintf('%s?%s', $requestUri, http_build_query($queryBody)),
                        'PATH_INFO' => $requestUri,
                        'QUERY_STRING' => http_build_query($queryBody),
                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken),
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
                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $accessToken),
                    ),
                    null,
                    Json::encode($queryBody)
                )
            )->getBody();
        }
    }
}
