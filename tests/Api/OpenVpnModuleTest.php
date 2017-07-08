<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests\Api;

use DateTime;
use PDO;
use PHPUnit_Framework_TestCase;
use Psr\Log\NullLogger;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Api\OpenVpnModule;
use SURFnet\VPN\Server\OpenVpn\ServerManager;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\Tests\TestSocket;

class OpenVpnModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $config = Config::fromFile(sprintf('%s/data/openvpn_module_config.php', __DIR__));
        $storage = new Storage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            new DateTime()
        );
        $storage->init();

        $storage->addCertificate('foo', 'f3bb6f8efb4dc64be35e1044cf1b5e76', 'Display Name', new DateTime('@12345678'), new DateTime('@23456789'));
        $storage->addCertificate('foo', '78f4a3c26062a434b01892e2b23126d1', 'Display Name 2', new DateTime('@12345678'), new DateTime('@23456789'));

        $serverManager = new ServerManager(
            $config,
            new TestSocket(),
            new NullLogger()
        );

        $this->service = new Service();
        $this->service->addModule(
            new OpenVpnModule(
                $serverManager,
                $storage
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
                            'common_name' => 'f3bb6f8efb4dc64be35e1044cf1b5e76',
                            'virtual_address' => [
                                '10.128.7.3',
                                'fd60:4a08:2f59:ba0::1001',
                            ],
                            'user_id' => 'foo',
                            'user_is_disabled' => '0',
                            'display_name' => 'Display Name',
                            'certificate_is_disabled' => '0',
                        ],
                        [
                            'common_name' => '78f4a3c26062a434b01892e2b23126d1',
                            'virtual_address' => [
                                '10.128.7.4',
                                'fd60:4a08:2f59:ba0::1002',
                            ],
                            'user_id' => 'foo',
                            'user_is_disabled' => '0',
                            'display_name' => 'Display Name 2',
                            'certificate_is_disabled' => '0',
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
