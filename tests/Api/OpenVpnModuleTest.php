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

require_once sprintf('%s/Test/TestSocket.php', dirname(__DIR__));

use PHPUnit_Framework_TestCase;
use Psr\Log\NullLogger;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\OpenVpn\ServerManager;
use SURFnet\VPN\Server\Test\TestSocket;

class OpenVpnModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $config = Config::fromFile(sprintf('%s/data/openvpn_module_config.yaml', __DIR__));

        $serverManager = new ServerManager(
            $config,
            new TestSocket(),
            new NullLogger()
        );

        $this->service = new Service();
        $this->service->addModule(
            new OpenVpnModule(
                $serverManager
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-admin-portal' => 'bbccdd',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testConnect()
    {
        $this->assertSame(
            [
                [
                    'id' => 'internet',
                    'connections' => [
                        [
                            'common_name' => 'fkooman_testdroid',
                            'proto' => 6,
                            'virtual_address' => [
                                'fd77:6bac:e591:8203::1001',
                                '10.120.188.195',
                            ],
                        ],
                        [
                            'common_name' => 'fkooman_lenovo_f24',
                            'proto' => 4,
                            'virtual_address' => [
                                '10.120.188.194',
                                'fd77:6bac:e591:8203::1000',
                            ],
                        ],
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-admin-portal', 'bbccdd'],
                'GET',
                'client_connections',
                [],
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
                    'PATH_INFO' => sprintf('/%s', $pathInfo),
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
