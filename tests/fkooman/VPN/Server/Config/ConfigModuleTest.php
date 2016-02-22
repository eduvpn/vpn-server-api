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

class ConfigModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $server;

    public function setUp()
    {
        // get a directory to play with
        $tempDirName = tempnam(sys_get_temp_dir(), 'static');
        if (file_exists($tempDirName)) {
            @unlink($tempDirName);
        }
        @mkdir($tempDirName);

        @file_put_contents($tempDirName.'/bar_foo', '{"v4": "10.42.42.18"}');

        // a not disabled commonName
        @file_put_contents($tempDirName.'/bar_baz', '{}');

        // a disabled commonName
        @file_put_contents($tempDirName.'/foo_baz', '{"disable": true}');

        // a static IP
        @file_put_contents($tempDirName.'/foo_bar', '{"v4": "10.42.42.15"}');

        $this->service = new Service();
        $this->service->addModule(
            new ConfigModule(
                new StaticConfig(
                    $tempDirName,
                    new IP('10.42.42.0/24'),
                     new IP('10.42.42.128/25')
                )
            )
        );
        $dummyAuth = new DummyAuthentication('foo');
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register($dummyAuth, 'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetStaticAddresses()
    {
        $this->assertSame(
            [
                'ok' => true,
                'ip' => [
                    'bar_foo' => [
                        'v4' => '10.42.42.18',
                    ],
                    'foo_bar' => [
                        'v4' => '10.42.42.15',
                    ],
                ],
                'ipRange' => '10.42.42.0/24',
                'poolRange' => '10.42.42.128/25',
            ],
            $this->makeRequest('GET', '/static/ip', [])
        );
    }

    public function testGetStaticAddressesForUser()
    {
        $this->assertSame(
            [
                'ok' => true,
                'ip' => [
                    'foo_bar' => [
                        'v4' => '10.42.42.15',
                    ],
                ],
                'ipRange' => '10.42.42.0/24',
                'poolRange' => '10.42.42.128/25',
            ],
            $this->makeRequest('GET', '/static/ip', ['user_id' => 'foo'])
        );
    }

    public function testGetStaticAddressesForCommonName()
    {
        $this->assertSame(
            [
                'ok' => true,
                'ip' => [
                    'v4' => '10.42.42.15',
                ],
                'ipRange' => '10.42.42.0/24',
                'poolRange' => '10.42.42.128/25',
            ],
            $this->makeRequest('GET', '/static/ip', ['common_name' => 'foo_bar'])
        );
    }

    public function testSetStaticAddress()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('POST', '/static/ip', ['common_name' => 'foo_bar', 'v4' => '10.42.42.44'])
        );
    }

    public function testSetStaticAddressNoCommonName()
    {
        $this->assertSame(
            [
                'error' => 'missing common_name',
            ],
            $this->makeRequest('POST', '/static/ip', ['v4' => '10.42.42.44'])
        );
    }

    public function testSetStaticAddressInvalidCommonName()
    {
        $this->assertSame(
            [
                'error' => 'invalid characters in common_name',
            ],
            $this->makeRequest('POST', '/static/ip', ['common_name' => 'foo+bar', 'v4' => '10.42.42.44'])
        );
    }

    public function testSetStaticAddressRemoveAssignment()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('POST', '/static/ip', ['common_name' => 'foo_bar'])
        );
    }

    public function testSetStaticAddressRemoveAssignmentEmpty()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('POST', '/static/ip', ['common_name' => 'foo_bar', 'v4' => ''])
        );
    }

    public function testDisableCommonName()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('POST', '/ccd/disable', ['common_name' => 'foo_bar'])
        );
    }

    public function testDisableCommonNameNoCommonName()
    {
        $this->assertSame(
            [
                'error' => 'missing common_name',
            ],
            $this->makeRequest('POST', '/ccd/disable')
        );
    }

    public function testEnableCommonName()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('DELETE', '/ccd/disable', ['common_name' => 'foo_baz'])
        );
    }

    public function testGetDisabledCommonNames()
    {
        $this->assertSame(
            [
                'ok' => true,
                'disabled' => [
                    'foo_baz',
                ],
            ],
            $this->makeRequest('GET', '/ccd/disable')
        );
    }

    public function testGetDisabledCommonNamesForUserId()
    {
        $this->assertSame(
            [
                'ok' => true,
                'disabled' => [
                    'foo_baz',
                ],
            ],
            $this->makeRequest('GET', '/ccd/disable', ['user_id' => 'foo'])
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
}
