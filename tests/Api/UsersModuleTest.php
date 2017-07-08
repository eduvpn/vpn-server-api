<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests\Api;

use DateTime;
use fkooman\OAuth\Client\AccessToken;
use Otp\Otp;
use ParagonIE\ConstantTime\Encoding;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Acl\Provider\StaticProvider;
use SURFnet\VPN\Server\Api\UsersModule;
use SURFnet\VPN\Server\Storage;

class UsersModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $storage = new Storage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            new DateTime()
        );
        $storage->init();
        $storage->addCertificate('foo', 'abcd1234', 'ABCD1234', new DateTime('@12345678'), new DateTime('@23456789'));
        $storage->disableUser('bar');
        $storage->setTotpSecret('bar', 'CN2XAL23SIFTDFXZ');

//        $vootToken = new AccessToken('12345', 'bearer', 'groups', null, new DateTime('2016-01-01'));
        $vootToken = AccessToken::fromJson(
            json_encode([
                'provider_id' => 'foo|bar',
                'user_id' => 'foo',
                'access_token' => '12345',
                'token_type' => 'bearer',
                'scope' => 'groups',
                'refresh_token' => null,
                'expires_in' => 3600,
                'issued_at' => '2016-01-01 00:00:00',
            ])
        );

        $storage->setVootToken('bar', $vootToken);

        // user "baz" has a secret, and already used a key for replay testing
        $storage->setTotpSecret('baz', 'SWIXJ4V7VYALWH6E');
        $otp = new Otp();
        $storage->recordTotpKey('baz', $otp->totp(Encoding::base32DecodeUpper('SWIXJ4V7VYALWH6E')));

        $config = Config::fromFile(sprintf('%s/data/user_groups_config.php', __DIR__));
        $groupProviders = [
            new StaticProvider(
                $config->getSection('groupProviders')->getSection('StaticProvider')
            ),
        ];

        $this->service = new Service();
        $this->service->addModule(
            new UsersModule(
                $config,
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
                [
                    'user_id' => 'foo',
                    'is_disabled' => false,
                    'has_yubi_key_id' => false,
                    'has_totp_secret' => false,
                ],
                [
                    'user_id' => 'bar',
                    'is_disabled' => true,
                    'has_yubi_key_id' => false,
                    'has_totp_secret' => true,
                ],
                [
                    'user_id' => 'baz',
                    'is_disabled' => false,
                    'has_yubi_key_id' => false,
                    'has_totp_secret' => true,
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'GET',
                'user_list',
                ['profile_id' => 'internet'],
                []
            )
        );
    }

    public function testSetTotpSecret()
    {
        $otp = new Otp();
        $totpSecret = 'MM7TTLHPA7WZOJFB';
        $totpKey = $otp->totp(Encoding::base32DecodeUpper($totpSecret));

        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'set_totp_secret',
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
        $totpKey = $otp->totp(Encoding::base32DecodeUpper($totpSecret));

        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'verify_totp_key',
                [],
                [
                    'user_id' => 'bar',
                    'totp_key' => $totpKey,
                ]
            )
        );
    }

    public function testVerifyOtpKeyWrong()
    {
        // in theory this totp_key, 123456 could be correct at one point in
        // time... then this test will fail!
        $this->assertSame(
            [
                'ok' => false,
                'error' => 'TOTP validation failed: invalid TOTP key',
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'verify_totp_key',
                [],
                [
                    'user_id' => 'bar',
                    'totp_key' => '123456',
                ]
            )
        );
    }

    public function testVerifyOtpKeyReplay()
    {
        $otp = new Otp();
        $totpKey = $otp->totp(Encoding::base32DecodeUpper('SWIXJ4V7VYALWH6E'));

        $this->assertSame(
            [
                'ok' => false,
                'error' => 'TOTP validation failed: TOTP key replay',
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'verify_totp_key',
                [],
                [
                    'user_id' => 'baz',
                    'totp_key' => $totpKey,
                ]
            )
        );
    }

    public function testHasTotpSecret()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                'has_totp_secret',
                [
                    'user_id' => 'bar',
                ],
                []
            )
        );
    }

    public function testDeleteTotpSecret()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                'delete_totp_secret',
                [],
                [
                    'user_id' => 'bar',
                ]
            )
        );
    }

    public function testSetVootToken()
    {
        //        $vootToken = new AccessToken('AT', 'bearer', 'groups', 'RT', new DateTime('2016-01-02'));
        $vootToken = AccessToken::fromJson(
            json_encode([
                'provider_id' => 'foo|bar',
                'user_id' => 'foo',
                'access_token' => 'AT',
                'token_type' => 'bearer',
                'scope' => 'groups',
                'refresh_token' => 'RT',
                'expires_in' => 3600,
                'issued_at' => '2016-01-02 00:00:00',
            ])
        );

        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'set_voot_token',
                [],
                [
                    'user_id' => 'foo',
                    'voot_token' => $vootToken->toJson(),
                ]
            )
        );
    }

    public function testDeleteVootToken()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                'delete_voot_token',
                [],
                [
                    'user_id' => 'bar',
                ]
            )
        );
    }

    public function testDisableUser()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                'disable_user',
                [],
                [
                    'user_id' => 'foo',
                ]
            )
        );
    }

    public function testEnableUser()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                'enable_user',
                [],
                [
                    'user_id' => 'bar',
                ]
            )
        );
    }

    public function testDeleteUser()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'POST',
                'delete_user',
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
                [
                    'id' => 'all',
                    'displayName' => 'All',
                ],
                [
                    'id' => 'employees',
                    'displayName' => 'Employees',
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                'user_groups',
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
                    'SCRIPT_NAME' => '/index.php',
                    'REQUEST_URI' => sprintf('/%s', $pathInfo),
                    'PHP_AUTH_USER' => $basicAuth[0],
                    'PHP_AUTH_PW' => $basicAuth[1],
                ],
                $getData,
                $postData
            )
        );

        $responseArray = json_decode($response->getBody(), true)[$pathInfo];
        if ($responseArray['ok']) {
            if (array_key_exists('data', $responseArray)) {
                return $responseArray['data'];
            }

            return true;
        }

        // in case of errors...
        return $responseArray;
    }
}
