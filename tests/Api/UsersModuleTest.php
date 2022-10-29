<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Tests\Api;

use DateTime;
use fkooman\Otp\Base32;
use fkooman\Otp\FrkOtp;
use fkooman\Otp\OtpInfo;
use LC\Common\Http\BasicAuthenticationHook;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Server\Api\UsersModule;
use LC\Server\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class UsersModuleTest extends TestCase
{
    /** @var \LC\Common\Http\Service */
    private $service;

    /** @var \LC\Server\Storage */
    private $storage;

    protected function setUp()
    {
        $this->storage = new Storage(
            new PDO('sqlite::memory:'),
            'schema'
        );
        $this->storage->setDateTime(new DateTime('2018-01-01T01:00:00+00:00'));
        $this->storage->init();
        $this->storage->addCertificate('foo', 'abcd1234', 'ABCD1234', new DateTime('@12345678'), new DateTime('@23456789'), null);
        $this->storage->disableUser('bar');
        $this->storage->disableUser('baz');
        $this->storage->enableUser('baz');
        $this->storage->setOtpSecret('bar', new OtpInfo('XO66UFFKDOWLG5LJP5TU2SCD7D4HKEM3', 'sha1', 6, 30));

        // user "baz" has a secret, and already used a key for replay testing
        $this->storage->setOtpSecret('baz', new OtpInfo('NTEVDXNSX5EXJQHOWDJBRB47EYGR5EED', 'sha1', 6, 30));
        $frkOtp = new FrkOtp();
        $dateTime = new DateTime();
        $totpKey = $frkOtp->totp(Base32::decodeUpper('NTEVDXNSX5EXJQHOWDJBRB47EYGR5EED'), 'sha1', 6, $dateTime->getTimestamp(), 30);

        $this->storage->recordOtpKey('baz', $totpKey, new DateTime('2018-01-01T08:00:00+00:00'));
        $this->storage->updateSessionInfo('bar', new DateTime('2018-01-01T02:00:00+00:00'), ['all', 'employees']);
        $usersModule = new UsersModule(
            $this->storage
        );
        $usersModule->setDateTime(new DateTime('2018-01-01T01:00:00+00:00'));
        $this->service = new Service();
        $this->service->addModule($usersModule);

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-user-portal' => 'aabbcc',
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
                    'has_totp_secret' => false,
                    'session_expires_at' => '2018-01-01T01:00:00+00:00',
                    'permission_list' => [],
                ],
                [
                    'user_id' => 'bar',
                    'is_disabled' => true,
                    'has_totp_secret' => true,
                    'session_expires_at' => '2018-01-01T02:00:00+00:00',
                    'permission_list' => ['all', 'employees'],
                ],
                [
                    'user_id' => 'baz',
                    'is_disabled' => false,
                    'has_totp_secret' => true,
                    'session_expires_at' => '2018-01-01T01:00:00+00:00',
                    'permission_list' => [],
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                'user_list',
                ['profile_id' => 'internet'],
                []
            )
        );
    }

    public function testSetOtpSecret()
    {
        $totpSecret = 'QFRMM4K7LOFDECURTGIL7MBJGKWLVQMC';
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
        $totpKey = $frkOtp->totp(Base32::decodeUpper('XO66UFFKDOWLG5LJP5TU2SCD7D4HKEM3'), 'sha1', 6, $dateTime->getTimestamp(), 30);

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
                'error' => 'TOTP validation failed: invalid OTP code',
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
        $totpKey = $frkOtp->totp(Base32::decodeUpper('NTEVDXNSX5EXJQHOWDJBRB47EYGR5EED'), 'sha1', 6, $dateTime->getTimestamp(), 30);

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
                ['vpn-user-portal', 'aabbcc'],
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
                ['vpn-user-portal', 'aabbcc'],
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
                ['vpn-user-portal', 'aabbcc'],
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
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'delete_user',
                [],
                [
                    'user_id' => 'foo',
                ]
            )
        );
    }

    public function testUpdateSessionInfo()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'user_update_session_info',
                [],
                [
                    'user_id' => 'foo',
                    'permission_list' => '["employee"]',
                    'session_expires_at' => '2018-01-01T08:00:00+00:00',
                ]
            )
        );
        $this->assertSame(
            [
                [
                    'id' => '1',
                    'type' => 'notification',
                    'message' => 'updated session info {permission_list: [employee], expires_at: 2018-01-01T08:00:00+00:00}',
                    'date_time' => '2018-01-01T01:00:00+00:00',
                ],
            ],
            $this->storage->userMessages('foo')
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
            if (\array_key_exists('data', $responseArray)) {
                return $responseArray['data'];
            }

            return true;
        }

        // in case of errors...
        return $responseArray;
    }
}
