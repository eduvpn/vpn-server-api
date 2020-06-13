<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Tests\Api;

use DateTime;
use LC\Common\Config;
use LC\Common\Http\BasicAuthenticationHook;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Server\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class ConnectionsModuleTest extends TestCase
{
    /** @var \LC\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $storage = new Storage(
            new PDO('sqlite::memory:'),
            'schema',
            new DateTime()
        );
        $storage->init();
        $storage->updateSessionInfo('foo', new DateTime('2018-01-01 00:00:00'), ['students']);

        $storage->addCertificate('foo', '12345678901234567890123456789012', '12345678901234567890123456789012', new DateTime('@12345678'), new DateTime('@23456789'), null);
        $storage->clientConnect('internet', '12345678901234567890123456789012', '10.10.10.10', 'fd00:4242:4242:4242::', new DateTime('@12345678'));

        $config = Config::fromFile(sprintf('%s/data/config.php', __DIR__));
        $connectionsModule = new TestConnectionsModule(
            $config,
            $storage
        );
        $connectionsModule->setDateTime(new DateTime('2018-01-01 00:00:00'));

        $this->service = new Service();
        $this->service->addModule($connectionsModule);

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
                    'connected_at' => '12345678',
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
                    'connected_at' => '12345678',
                ]
            )
        );
    }

    public function testConnectNotInAcl()
    {
        $this->assertSame(
            [
                'ok' => false,
                'error' => '[CONNECT] ERROR: unable to connect, user permissions are [students], but requires any of [employees]',
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
                    'connected_at' => '12345678',
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
                    'connected_at' => '12345678',
                    'disconnected_at' => '23456789',
                    'bytes_transferred' => '2222222',
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
            if (\array_key_exists('data', $responseArray)) {
                return $responseArray['data'];
            }

            return true;
        }

        // in case of errors...
        return $responseArray;
    }
}
