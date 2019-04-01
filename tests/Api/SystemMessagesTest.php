<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Tests\Api;

use DateTime;
use LC\Common\Http\BasicAuthenticationHook;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Server\Api\SystemMessagesModule;
use LC\Server\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class SystemMessagesTest extends TestCase
{
    /** @var \LC\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $storage = new Storage(
            new PDO('sqlite::memory:'),
            'schema'
        );
        $storage->setDateTime(new DateTime('2016-01-01 08:00:00'));
        $storage->init();
        $storage->addSystemMessage('motd', 'Hello World!');

        $this->service = new Service();
        $this->service->addModule(
            new SystemMessagesModule(
                $storage
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-user-portal' => 'aabbcc',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testGetSystemMessages()
    {
        $this->assertSame(
            [
                [
                    'id' => '1',
                    'message' => 'Hello World!',
                    'date_time' => '2016-01-01 08:00:00',
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                'system_messages',
                ['message_type' => 'motd'],
                []
            )
        );
    }

    public function testAddSystemMessage()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'add_system_message',
                [],
                ['message_type' => 'motd', 'message_body' => 'foo']
            )
        );
        $this->assertSame(
            [
                [
                    'id' => '1',
                    'message' => 'Hello World!',
                    'date_time' => '2016-01-01 08:00:00',
                ],
                [
                    'id' => '2',
                    'message' => 'foo',
                    'date_time' => '2016-01-01 08:00:00',
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                'system_messages',
                ['message_type' => 'motd'],
                []
            )
        );
    }

    public function testDeleteSystemMessage()
    {
        $this->assertTrue(
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'POST',
                'delete_system_message',
                [],
                ['message_id' => 1]
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
