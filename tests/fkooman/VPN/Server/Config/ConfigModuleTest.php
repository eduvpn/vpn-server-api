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
use fkooman\Json\Json;

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

    // COMMON_NAMES | ADMIN

    public function testGetCnConfigAdmin()
    {
        $this->assertSame(
            [
                'disable' => true,
            ],
            $this->makeRequest('GET', '/config/common_names/foo_bar', [], 'admin')
        );
    }

    public function testPutCnConfigAdmin()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/common_names/foo_bar', ['pool' => 'v6'], 'admin')
        );
    }

    public function testGetConfigsAdmin()
    {
        $this->assertSame(
            [
                'items' => [
                    'foo_bar' => [
                        'disable' => true,
                    ],
                    'foo_baz' => [
                        'disable' => false,
                    ],
                    'bar_foo' => [
                        'disable' => false,
                    ],
                ],
            ],
            $this->makeRequest('GET', '/config/common_names/', [], 'admin')
        );
    }

    public function testGetConfigsPerUserAdmin()
    {
        $this->assertSame(
            [
                'items' => [
                    'bar_foo' => [
                        'disable' => false,
                    ],
                ],
            ],
            $this->makeRequest('GET', '/config/common_names/', ['user_id' => 'bar'], 'admin')
        );
    }

    public function testGetCnConfigPortal()
    {
        $this->assertSame(
            [
                'disable' => true,
            ],
            $this->makeRequest('GET', '/config/common_names/foo_bar', [], 'portal')
        );
    }

    public function testPutCnConfigPortal()
    {
        $this->assertSame(
            [
                'error' => 'insufficient_scope',
                'error_description' => '"admin" scope required',
            ],
            $this->makeRequest('PUT', '/config/common_names/foo_bar', ['pool' => 'v6'], 'portal')
        );
    }

    public function testGetConfigsPortal()
    {
        $this->assertSame(
            [
                'error' => 'insufficient_scope',
                'error_description' => '"admin" scope required',
            ],
            $this->makeRequest('GET', '/config/common_names/', [], 'portal')
        );
    }

    public function testGetConfigsPerUserPortal()
    {
        $this->assertSame(
            [
                'items' => [
                    'bar_foo' => [
                        'disable' => false,
                    ],
                ],
            ],
            $this->makeRequest('GET', '/config/common_names/', ['user_id' => 'bar'], 'portal')
        );
    }

    // USERS

    public function testGetUserAdmin()
    {
        $this->assertSame(
            [
                'disable' => false,
                'otp_secret' => false,
            ],
            $this->makeRequest('GET', '/config/users/foo', [], 'admin')
        );
    }

    public function testDisableUserAdmin()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/users/foo', ['disable' => true], 'admin')
        );
    }

    public function testDisableOtpSecretAdmin()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/users/baz', ['otp_secret' => false], 'admin')
        );
    }

    public function testGetUserPortal()
    {
        $this->assertSame(
            [
                'disable' => false,
                'otp_secret' => false,
            ],
            $this->makeRequest('GET', '/config/users/foo', [], 'portal')
        );
    }

    public function testDisableUserPortal()
    {
        $this->assertSame(
            [
                'error' => 'insufficient_scope',
                'error_description' => '"admin" scope required',
            ],
            $this->makeRequest('PUT', '/config/users/foo', ['disable' => true], 'portal')
        );
    }

    public function testDisableOtpSecretPortal()
    {
        $this->assertSame(
            [
                'error' => 'insufficient_scope',
                'error_description' => '"admin" scope required',
            ],
            $this->makeRequest('PUT', '/config/users/baz', ['otp_secret' => false], 'portal')
        );
    }

    public function testSetOtpSecretPortal()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/users/foo', ['otp_secret' => '7ZCJEKXKHJVDZXXX'], 'portal')
        );
    }

    public function testSetOtpSecretWhenAlreadySetPortal()
    {
        $this->assertSame(
            [
                'error' => 'insufficient_scope',
                'error_description' => '"admin" scope required',
            ],
            $this->makeRequest('PUT', '/config/users/baz', ['otp_secret' => '8ZCJEKXKHJVDZXXX'], 'portal')
        );
    }

    public function testSetOtpDisableWhenAlreadySetPortal()
    {
        $this->assertSame(
            [
                'error' => 'insufficient_scope',
                'error_description' => '"admin" scope required',
            ],
            $this->makeRequest('PUT', '/config/users/baz', ['otp_secret' => false], 'portal')
        );
    }

    public function testSetOtpTrueWhenAlreadySetPortal()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('PUT', '/config/users/baz', ['otp_secret' => true], 'portal')
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
