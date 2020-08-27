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
use LC\Server\Api\CertificatesModule;
use LC\Server\Storage;
use LC\Server\Tests\TestCa;
use LC\Server\Tests\TestRandom;
use LC\Server\TlsCrypt;
use PDO;
use PHPUnit\Framework\TestCase;

class CertificatesModuleTest extends TestCase
{
    /** @var \LC\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $random = new TestRandom(['random_1', 'random_2']);
        $storage = new Storage(
            new PDO('sqlite::memory:'),
            'schema',
            new DateTime()
        );
        $storage->init();
        $this->service = new Service();
        $this->service->addModule(
            new CertificatesModule(
                Config::fromFile(\dirname(\dirname(__DIR__)).'/config/config.php.example'),
                new TestCa(),
                $storage,
                new TlsCrypt(sprintf('%s/data', \dirname(__DIR__))),
                $random
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-user-portal' => 'abcdef',
                'vpn-server-node' => 'aabbcc',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testGenerateCert()
    {
        $expiresAt = new DateTime('@2345678901');
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
                ['user_id' => 'foo', 'display_name' => 'bar', 'expires_at' => $expiresAt->format(DateTime::ATOM)]
            )
        );
    }

    public function testServerInfo()
    {
        $testKey = <<< 'EOF'
#
# 2048 bit OpenVPN static key
#
-----BEGIN OpenVPN Static key V1-----
TEST
-----END OpenVPN Static key V1-----

EOF;

        $this->assertSame(
            [
                'tls_crypt' => $testKey,
                'ca' => 'Ca',
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'abcdef'],
                'GET',
                'server_info',
                ['profile_id' => 'internet'],
                []
            )
        );
    }

    public function testGenerateServerCert()
    {
        $testKey = <<< 'EOF'
#
# 2048 bit OpenVPN static key
#
-----BEGIN OpenVPN Static key V1-----
TEST
-----END OpenVPN Static key V1-----

EOF;

        $this->assertSame(
            [
                'certificate' => 'ServerCert for vpn.example',
                'private_key' => 'ServerCert for vpn.example',
                'valid_from' => 1234567890,
                'valid_to' => 2345678901,
                'tls_crypt' => $testKey,
                'ca' => 'Ca',
            ],
            $this->makeRequest(
                ['vpn-server-node', 'aabbcc'],
                'POST',
                'add_server_certificate',
                [],
                [
                    'profile_id' => 'internet',
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
