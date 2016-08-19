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
use Psr\Log\NullLogger;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;
use fkooman\VPN\Server\Disable;
use fkooman\IO\IO;

class CommonNamesModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $service;

    public function setUp()
    {
        $io = new IO();
        $configDir = sprintf('%s/%s/common_names', sys_get_temp_dir(), $io->getRandom());
        $io->writeFile(sprintf('%s/foo_bar', $configDir), null, true);

        $module = new CommonNamesModule(
            new Disable($configDir),
            new NullLogger()
        );

        $this->service = new Service();
        $this->service->addModule($module);
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

    public function testGetDisabled()
    {
        $this->assertSame(
            [
                'data' => [
                    'common_names' => ['foo_bar'],
                ],
            ],
            $this->makeRequest('GET', '/common_names/disabled', 'admin')
        );
    }

    public function testGetDisable()
    {
        $this->assertSame(
            [
                'data' => [
                    'disabled' => true,
                ],
            ],
            $this->makeRequest('GET', '/common_names/disabled/foo_bar', 'admin')
        );
    }

    public function testGetDisableNonExisting()
    {
        $this->assertSame(
            [
                'data' => [
                    'disabled' => false,
                ],
            ],
            $this->makeRequest('GET', '/common_names/disabled/bar_foo', 'admin')
        );
    }

    public function testSetDisableTrue()
    {
        $this->assertSame(
            [
                'data' => [
                    'disabled' => false,
                ],
            ],
            $this->makeRequest('GET', '/common_names/disabled/bar_foo', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('POST', '/common_names/disabled/bar_foo', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'disabled' => true,
                ],
            ],
            $this->makeRequest('GET', '/common_names/disabled/bar_foo', 'admin')
        );
    }

    public function testSetDisableFalse()
    {
        $this->assertSame(
            [
                'data' => [
                    'disabled' => true,
                ],
            ],
            $this->makeRequest('GET', '/common_names/disabled/foo_bar', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('DELETE', '/common_names/disabled/foo_bar', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'disabled' => false,
                ],
            ],
            $this->makeRequest('GET', '/common_names/disabled/foo_bar', 'admin')
        );
    }

    private function makeRequest($requestMethod, $requestUri, $accessToken)
    {
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
                )
            )
        )->getBody();
    }
}
