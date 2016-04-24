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

namespace fkooman\VPN\Server\Log;

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use PDO;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;

class LogModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $server;

    public function setUp()
    {
        $connectionLog = new ConnectionLog(new PDO('sqlite::memory:'));
        $connectionLog->initDatabase();

        $connectionLog->connect(
            [
                'common_name' => 'foo_vpn_ex_def',
                'time_unix' => '1000000000',
                'v4' => '10.42.42.2',
                'v6' => 'fd00:4242:4242::2',
            ]
        );

        $connectionLog->disconnect(
            [
                'common_name' => 'foo_vpn_ex_def',
                'time_unix' => '1000000000',
                'disconnect_time_unix' => '1000010000',
                'v4' => '10.42.42.2',
                'v6' => 'fd00:4242:4242::2',
                'bytes_received' => '4843',
                'bytes_sent' => '5317',
            ]
        );

        $dateTime = $this->getMockBuilder('DateTime')->getMock();
        $dateTime->method('getTimeStamp')->will($this->returnValue(1000020000));
        $logModule = new LogModule($connectionLog, $dateTime);

        $this->service = new Service();
        $this->service->addModule($logModule);
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register(
            new BearerAuthentication(
                new ArrayBearerValidator(
                    [
                        'vpn-user-portal' => [
                            'token' => 'aabbcc',
                            'scope' => 'admin portal',
                        ],
                    ]
                )
            ),
            'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetLogHistory()
    {
        $this->assertSame(
            [
                'ok' => true,
                'history' => [
                    [
                        'common_name' => 'foo_vpn_ex_def',
                        'time_unix' => '1000000000',
                        'v4' => '10.42.42.2',
                        'v6' => 'fd00:4242:4242::2',
                        'bytes_received' => '4843',
                        'bytes_sent' => '5317',
                        'disconnect_time_unix' => '1000010000',
                    ],
                ],
            ],
            $this->makeRequest('GET', sprintf('/log/%s', date('Y-m-d', 1000010001)))
        );
    }

    public function testGetLogHistoryForDate()
    {
        $this->assertSame(
            [
                'ok' => true,
                'history' => [
                ],
            ],
            $this->makeRequest('GET', sprintf('/log/%s', date('Y-m-d', 999888888)))
        );
    }

    public function testGetLogHistoryForDateOutOfRange()
    {
        $this->assertSame(
            [
                'error' => 'invalid date range',
            ],
            $this->makeRequest('GET', sprintf('/log/%s', date('Y-m-d', 1234567890)))
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
                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', 'aabbcc'),
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
                        'HTTP_AUTHORIZATION' => sprintf('Bearer %s', 'aabbcc'),
                    ),
                    $queryBody
                )
            )->getBody();
        }
    }
}
