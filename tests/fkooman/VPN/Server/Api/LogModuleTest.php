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
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;

class LogModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $server;

    public function setUp()
    {
        $logDir = __DIR__.'/data/log';
        $logModule = new LogModule($logDir);

        $this->service = new Service();
        $this->service->addModule($logModule);
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register(
            new BearerAuthentication(
                new ArrayBearerValidator(
                    [
                        'vpn-user-portal' => [
                            'token' => 'aabbcc',
                            'scope' => 'admin',
                        ],
                    ]
                )
            ),
            'api'
        );
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testGetLogEntry()
    {
        $dateTime = '2016-08-05T09:44:18+02:00';
        $ipAddress = 'fdc6:6794:d2bf:1::1000';

        $this->assertSame(
            [
                'data' => [
                    'log' => [
                        [
                            'user_id' => 'fkooman',
                            'v4' => '10.73.218.66',
                            'v6' => 'fdc6:6794:d2bf:1::1000',
                            'config_name' => 'i9300',
                            'connect_time' => 1470383058,
                            'disconnect_time' => 1470383221,
                        ],
                    ],
                ],
            ],
            $this->makeRequest('/log', ['date_time' => $dateTime, 'ip_address' => $ipAddress])
        );
    }

    public function testGetNonExistingLogEntry()
    {
        // wrong year...
        $dateTime = '2015-08-05T09:44:18+02:00';
        $ipAddress = 'fdc6:6794:d2bf:1::1000';

        $this->assertSame(
            [
                'data' => [
                    'log' => [
                        // is empty now!
                    ],
                ],
            ],
            $this->makeRequest('/log', ['date_time' => $dateTime, 'ip_address' => $ipAddress])
        );
    }

    private function makeRequest($requestUri, array $queryBody = [])
    {
        return $this->service->run(
            new Request(
                array(
                    'SERVER_NAME' => 'www.example.org',
                    'SERVER_PORT' => 80,
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => sprintf('%s?%s', $requestUri, http_build_query($queryBody)),
                    'PATH_INFO' => $requestUri,
                    'QUERY_STRING' => http_build_query($queryBody),
                    'HTTP_AUTHORIZATION' => sprintf('Bearer %s', 'aabbcc'),
                )
            )
        )->getBody();
    }
}
