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

use DateTime;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Storage;

class SystemMessagesTest extends PHPUnit_Framework_TestCase
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
            new DateTime('2016-01-01 08:00:00')
        );
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
                'vpn-admin-portal' => 'aabbcc',
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
                ['vpn-admin-portal', 'aabbcc'],
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
                ['vpn-admin-portal', 'aabbcc'],
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
                ['vpn-admin-portal', 'aabbcc'],
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
                ['vpn-admin-portal', 'aabbcc'],
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
            if (array_key_exists('data', $responseArray)) {
                return $responseArray['data'];
            }

            return true;
        }

        // in case of errors...
        return $responseArray;
    }
}
