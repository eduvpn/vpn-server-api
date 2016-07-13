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
use fkooman\VPN\Server\Acl\StaticAcl;
use fkooman\VPN\Server\OtpSecret;
use fkooman\VPN\Server\VootToken;
use fkooman\Config\Reader;
use fkooman\Config\ArrayReader;

class UsersModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $service;

    public function setUp()
    {
        $io = new IO();
        $disabledConfigDir = sprintf('%s/%s/users/disabled', sys_get_temp_dir(), $io->getRandom());
        $otpSecretConfigDir = sprintf('%s/%s/users/otp_secrets', sys_get_temp_dir(), $io->getRandom());
        $vootTokenConfigDir = sprintf('%s/%s/users/voot_tokens', sys_get_temp_dir(), $io->getRandom());

        $io->writeFile(sprintf('%s/foo', $disabledConfigDir), null, true);
        $io->writeFile(sprintf('%s/foo', $otpSecretConfigDir), 'SECRET', true);
        $io->writeFile(sprintf('%s/foo', $vootTokenConfigDir), 'TOKEN', true);

        $module = new UsersModule(
            new Disable($disabledConfigDir),
            new OtpSecret($otpSecretConfigDir),
            new VootToken($vootTokenConfigDir),
            new StaticAcl(
                new Reader(
                    new ArrayReader(
                        [
                            'StaticAcl' => [
                                'default' => [
                                    'displayName' => 'Default',
                                    'members' => ['foo', 'bar', 'baz'],
                                ],
                                'p2p' => [
                                    'displayName' => 'P2P',
                                    'members' => ['foo'],
                                ],
                            ],
                        ]
                    )
                )
            ),
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
                    'users' => ['foo'],
                ],
            ],
            $this->makeRequest('GET', '/users/disabled', 'admin')
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
            $this->makeRequest('GET', '/users/disabled/foo', 'admin')
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
            $this->makeRequest('GET', '/users/disabled/bar', 'admin')
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
            $this->makeRequest('GET', '/users/disabled/bar', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('POST', '/users/disabled/bar', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'disabled' => true,
                ],
            ],
            $this->makeRequest('GET', '/users/disabled/bar', 'admin')
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
            $this->makeRequest('GET', '/users/disabled/foo', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('DELETE', '/users/disabled/foo', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'disabled' => false,
                ],
            ],
            $this->makeRequest('GET', '/users/disabled/foo', 'admin')
        );
    }

    public function testGetOtpSecrets()
    {
        $this->assertSame(
            [
                'data' => [
                    'users' => ['foo'],
                ],
            ],
            $this->makeRequest('GET', '/users/otp_secrets', 'admin')
        );
    }

    public function testGetOtpSecret()
    {
        $this->assertSame(
            [
                'data' => [
                    'otp_secret' => true,
                ],
            ],
            $this->makeRequest('GET', '/users/otp_secrets/foo', 'admin')
        );
    }

    public function testSetOtpSecret()
    {
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('POST', '/users/otp_secrets/bar', 'portal', ['otp_secret' => 'ABCDEFGHIKJLMNOP'])
        );
    }

    public function testDeleteOtpSecret()
    {
        $this->assertSame(
            [
                'data' => [
                    'ok' => false,
                ],
            ],
            $this->makeRequest('DELETE', '/users/otp_secrets/bar', 'admin')
        );
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('DELETE', '/users/otp_secrets/foo', 'admin')
        );
    }

    public function testOverwriteOtpSecret()
    {
        $this->assertSame(
            [
                'data' => [
                    'ok' => false,
                ],
            ],
            $this->makeRequest('POST', '/users/otp_secrets/foo', 'portal', ['otp_secret' => 'ABCDEFGHIKJLMNOP'])
        );
    }

    public function testGetGroups()
    {
        $this->assertEquals(
            [
                'data' => [
                    'groups' => [
                        [
                            'id' => 'default',
                            'displayName' => 'Default',
                        ],
                        [
                            'id' => 'p2p',
                            'displayName' => 'P2P',
                        ],
                    ],
                ],
            ],
            $this->makeRequest('GET', '/users/groups/foo', 'portal')
        );
    }

    public function testSetVootToken()
    {
        $this->assertSame(
            [
                'data' => [
                    'ok' => true,
                ],
            ],
            $this->makeRequest('POST', '/users/voot_tokens/bar', 'portal', ['voot_token' => 'VOOTTOKEN'])
        );
    }

    public function testGetVootToken()
    {
        $this->assertSame(
            [
                'data' => [
                    'voot_token' => true,
                ],
            ],
            $this->makeRequest('GET', '/users/voot_tokens/foo', 'portal')
        );
    }

    private function makeRequest($requestMethod, $requestUri, $accessToken, array $postData = [])
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
                ),
                $postData
            )
        )->getBody();
    }
}
