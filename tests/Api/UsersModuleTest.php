<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests\Api;

use DateTime;
use fkooman\Otp\FrkOtp;
use fkooman\Otp\OtpInfo;
use ParagonIE\ConstantTime\Base32;
use PDO;
use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Acl\Provider\StaticProvider;
use SURFnet\VPN\Server\Api\UsersModule;
use SURFnet\VPN\Server\Storage;

class UsersModuleTest extends TestCase
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
            'schema',
            new DateTime('2018-01-01 01:00:00')
        );
        $storage->init();
        $storage->addCertificate('foo', 'abcd1234', 'ABCD1234', new DateTime('@12345678'), new DateTime('@23456789'), null);
        $storage->disableUser('bar');
        $storage->disableUser('baz');
        $storage->enableUser('baz');
        $storage->setOtpSecret('bar', new OtpInfo('CN2XAL23SIFTDFXZ', 'sha1', 6, 30));

        // user "baz" has a secret, and already used a key for replay testing
        $storage->setOtpSecret('baz', new OtpInfo('SWIXJ4V7VYALWH6E', 'sha1', 6, 30));
        $frkOtp = new FrkOtp();
        $dateTime = new DateTime();
        $totpKey = $frkOtp->totp(Base32::decodeUpper('SWIXJ4V7VYALWH6E'), 'sha1', 6, $dateTime->getTimestamp(), 30);

        $storage->recordOtpKey('baz', $totpKey, new DateTime('2018-01-01 08:00:00'));

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
                'vpn-server-node' => 'ccddee',
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
                    'last_authenticated_at' => null,
                    'entitlement_list' => [],
                ],
                [
                    'user_id' => 'bar',
                    'is_disabled' => true,
                    'has_yubi_key_id' => false,
                    'has_totp_secret' => true,
                    'last_authenticated_at' => null,
                    'entitlement_list' => [],
                ],
                [
                    'user_id' => 'baz',
                    'is_disabled' => false,
                    'has_yubi_key_id' => false,
                    'has_totp_secret' => true,
                    'last_authenticated_at' => null,
                    'entitlement_list' => [],
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

    public function testSetOtpSecret()
    {
        $totpSecret = 'MM7TTLHPA7WZOJFB';
        $frkOtp = new FrkOtp();
        $dateTime = new DateTime();
        $totpKey = $frkOtp->totp(Base32::decodeUpper($totpSecret), 'sha1', 6, $dateTime->getTimestamp(), 30);

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
        $frkOtp = new FrkOtp();
        $dateTime = new DateTime();
        $totpKey = $frkOtp->totp(Base32::decodeUpper('CN2XAL23SIFTDFXZ'), 'sha1', 6, $dateTime->getTimestamp(), 30);

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
                    'totp_key' => '001122',
                ]
            )
        );
    }

    public function testVerifyOtpKeyReplay()
    {
        $frkOtp = new FrkOtp();
        $dateTime = new DateTime();
        $totpKey = $frkOtp->totp(Base32::decodeUpper('SWIXJ4V7VYALWH6E'), 'sha1', 6, $dateTime->getTimestamp(), 30);

        $this->assertSame(
            [
                'ok' => false,
                'error' => 'TOTP validation failed: replay of OTP code',
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
            ['all', 'employees'],
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

    public function testLastAuthenticatedAtPing()
    {
        $this->assertNull(
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'GET',
                'user_list',
                [],
                []
            )[0]['last_authenticated_at']
        );
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'last_authenticated_at_ping',
                [],
                ['user_id' => 'foo', 'entitlement_list' => '[]']
            )
        );
        $this->assertSame(
            [
                'user_id' => 'foo',
                'is_disabled' => false,
                'has_yubi_key_id' => false,
                'has_totp_secret' => false,
                'last_authenticated_at' => '2018-01-01 01:00:00',
                'entitlement_list' => [],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'GET',
                'user_list',
                [],
                []
            )[0]
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
