<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests\Api;

use DateTime;
use fkooman\Otp\OtpInfo;
use PDO;
use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Acl\Provider\EntitlementProvider;
use SURFnet\VPN\Server\Api\ConnectionsModule;
use SURFnet\VPN\Server\Storage;

class ConnectionsModuleTest extends TestCase
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
            new DateTime()
        );
        $storage->init();
        $storage->lastAuthenticatedAtPing('foo', ['students']);
        $storage->addCertificate('foo', '12345678901234567890123456789012', '12345678901234567890123456789012', new DateTime('@12345678'), new DateTime('@23456789'), null);
        $storage->setOtpSecret('foo', new OtpInfo('CN2XAL23SIFTDFXZ', 'sha1', 6, 30));
        $storage->clientConnect('internet', '12345678901234567890123456789012', '10.10.10.10', 'fd00:4242:4242:4242::', new DateTime('@12345678'));

        $config = Config::fromFile(sprintf('%s/data/config.php', __DIR__));

        $groupProviders = [
            new EntitlementProvider($storage),
        ];

        $this->service = new Service();
        $this->service->addModule(
            new ConnectionsModule(
                $config,
                $storage,
                $groupProviders
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-server-node' => 'aabbcc',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testConnect()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                'connect',
                [],
                [
                    'profile_id' => 'internet',
                    'common_name' => '12345678901234567890123456789012',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                ]
            )
        );
    }

    public function testConnectInAcl()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                'connect',
                [],
                [
                    'profile_id' => 'acl',
                    'common_name' => '12345678901234567890123456789012',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                ]
            )
        );
    }

    public function testConnectNotInAcl()
    {
        $this->assertSame(
            [
                'ok' => false,
                'error' => '[VPN] unable to connect, account not a member of required group',
            ],
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                'connect',
                [],
                [
                    'profile_id' => 'acl2',
                    'common_name' => '12345678901234567890123456789012',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                ]
            )
        );
    }

    public function testDisconnect()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                'disconnect',
                [],
                [
                    'profile_id' => 'internet',
                    'common_name' => '12345678901234567890123456789012',
                    'ip4' => '10.10.10.10',
                    'ip6' => 'fd00:4242:4242:4242::',
                    'connected_at' => 12345678,
                    'disconnected_at' => 23456789,
                    'bytes_transferred' => 2222222,
                ]
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
