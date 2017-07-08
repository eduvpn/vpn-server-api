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
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Api\CertificatesModule;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\Tests\TestCa;
use SURFnet\VPN\Server\TlsAuth;

class CertificatesModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $random = $this->getMockBuilder('SURFnet\VPN\Common\RandomInterface')->getMock();
        $random->method('get')->will($this->onConsecutiveCalls('random_1', 'random_2'));

        $storage = new Storage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            new DateTime()
        );
        $storage->init();
        $this->service = new Service();
        $this->service->addModule(
            new CertificatesModule(
                new TestCa(),
                $storage,
                new TlsAuth(sprintf('%s/data', dirname(__DIR__))),
                $random
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-user-portal' => 'abcdef',
                'vpn-admin-portal' => 'ffeedd',
                'vpn-server-node' => 'aabbcc',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testGenerateCert()
    {
        $this->assertSame(
            [
                'certificate' => 'ClientCert for random_1',
                'private_key' => 'ClientKey for random_1',
                'valid_from' => 1234567890,
                'valid_to' => 2345678901,
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'abcdef'],
                'POST',
                'add_client_certificate',
                [],
                ['user_id' => 'foo', 'display_name' => 'bar']
            )
        );
    }

    public function testServerInfo()
    {
        $this->assertSame(
            [
                'ta' => 'Test_Ta_Key',
                'ca' => 'Ca',
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'abcdef'],
                'GET',
                'server_info',
                [],
                []
            )
        );
    }

    public function testGenerateServerCert()
    {
        $this->assertSame(
            [
                'certificate' => 'ServerCert for vpn.example',
                'private_key' => 'ServerCert for vpn.example',
                'valid_from' => 1234567890,
                'valid_to' => 2345678901,
                'ta' => 'Test_Ta_Key',
                'ca' => 'Ca',
            ],
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                'add_server_certificate',
                [],
                ['common_name' => 'vpn.example']
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
