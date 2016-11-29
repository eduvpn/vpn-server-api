<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Server\Api;

use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\Acl\Provider\StaticProvider;
use PDO;
use Otp\Otp;
use Base32\Base32;
use SURFnet\VPN\Common\Config;

class UsersModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $random = $this->getMockBuilder('SURFnet\VPN\Common\RandomInterface')->getMock();
        $random->method('get')->will($this->onConsecutiveCalls('random_1', 'random_2'));

        $storage = new Storage(
            new PDO('sqlite::memory:'),
            $random
        );
        $storage->init();

        $storage->addCertificate('foo', 'abcd1234', 'ABCD1234', 12345678, 23456789);
        $storage->disableUser('bar');
        $storage->setTotpSecret('bar', 'CN2XAL23SIFTDFXZ');
        $storage->setVootToken('bar', '123456');

        $config = Config::fromFile(sprintf('%s/data/user_groups_config.yaml', __DIR__));
        $groupProviders = [
            new StaticProvider(
                new Config($config->v('groupProviders', 'StaticProvider'))
            ),
        ];

        $this->service = new Service();
        $this->service->addModule(
            new UsersModule(
                $storage,
                $groupProviders
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-user-portal' => 'aabbcc',
                'vpn-admin-portal' => 'bbccdd',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testListUsers()
    {
        $this->assertSame(
            [
                'data' => [
                    'user_list' => [
                        [
                            'user_id' => 'foo',
                            'is_disabled' => false,
                        ],
                        [
                            'user_id' => 'bar',
                            'is_disabled' => false,
                        ],
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'GET',
                '/user_list',
                ['profile_id' => 'internet'],
                []
            )
        );
    }

    public function testSetTotpSecret()
    {
        $otp = new Otp();
        $totpSecret = 'MM7TTLHPA7WZOJFB';
        $totpKey = $otp->totp(Base32::decode($totpSecret));

        $this->assertSame(
            [
                'data' => [
                    'set_totp_secret' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                '/set_totp_secret',
                [],
                [
                    'user_id' => 'foo',
                    'totp_secret' => $totpSecret,
                    'totp_key' => $totpKey,
                ]
            )
        );
    }

    public function testVerifyOtpKey()
    {
        $otp = new Otp();
        $totpSecret = 'CN2XAL23SIFTDFXZ';
        $totpKey = $otp->totp(Base32::decode($totpSecret));

        $this->assertSame(
            [
                'data' => [
                    'verify_totp_key' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                '/verify_totp_key',
                [],
                [
                    'user_id' => 'bar',
                    'totp_key' => $totpKey,
                ]
            )
        );
    }

    public function testHasTotpSecret()
    {
        $this->assertSame(
            [
                'data' => [
                    'has_totp_secret' => true,
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                '/has_totp_secret',
                [
                    'user_id' => 'bar',
                ],
                []
            )
        );
    }

    public function testDeleteTotpSecret()
    {
        $this->assertSame(
            [
                'data' => [
                    'delete_totp_secret' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                '/delete_totp_secret',
                [],
                [
                    'user_id' => 'bar',
                ]
            )
        );
    }

    public function testSetVootToken()
    {
        $this->assertSame(
            [
                'data' => [
                    'set_voot_token' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                '/set_voot_token',
                [],
                [
                    'user_id' => 'foo',
                    'voot_token' => 'bar',
                ]
            )
        );
    }

    public function testDeleteVootToken()
    {
        $this->assertSame(
            [
                'data' => [
                    'delete_voot_token' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                '/delete_voot_token',
                [],
                [
                    'user_id' => 'bar',
                ]
            )
        );
    }

    public function testDisableUser()
    {
        $this->assertSame(
            [
                'data' => [
                    'disable_user' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                '/disable_user',
                [],
                [
                    'user_id' => 'foo',
                ]
            )
        );
    }

    public function testEnableUser()
    {
        $this->assertSame(
            [
                'data' => [
                    'enable_user' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                '/enable_user',
                [],
                [
                    'user_id' => 'bar',
                ]
            )
        );
    }

    public function testDeleteUser()
    {
        $this->assertSame(
            [
                'data' => [
                    'delete_user' => [
                        'ok' => true,
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                '/delete_user',
                [],
                [
                    'user_id' => 'foo',
                ]
            )
        );
    }

    public function testUserGroups()
    {
        $this->assertSame(
            [
                'data' => [
                    'user_groups' => [
                        [
                            'id' => 'all',
                            'displayName' => 'All',
                        ],
                        [
                            'id' => 'employees',
                            'displayName' => 'Employees',
                        ],
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                '/user_groups',
                [
                    'user_id' => 'bar',
                ],
                []
            )
        );
    }

    private function makeRequest(array $basicAuth, $requestMethod, $pathInfo, array $getData = [], array $postData = [])
    {
        $response = $this->service->run(
            new Request(
                [
                    'SERVER_PORT' => 80,
                    'SERVER_NAME' => 'vpn.example',
                    'REQUEST_METHOD' => $requestMethod,
                    'PATH_INFO' => $pathInfo,
                    'REQUEST_URI' => $pathInfo,
                    'PHP_AUTH_USER' => $basicAuth[0],
                    'PHP_AUTH_PW' => $basicAuth[1],
                ],
                $getData,
                $postData
            )
        );

        return json_decode($response->getBody(), true);
    }
}
