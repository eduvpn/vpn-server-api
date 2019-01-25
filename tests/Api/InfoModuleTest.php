<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Server\Tests\Api;

use LetsConnect\Common\Config;
use LetsConnect\Common\Http\BasicAuthenticationHook;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Server\Api\InfoModule;
use PHPUnit\Framework\TestCase;

class InfoModuleTest extends TestCase
{
    /** @var \LetsConnect\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $config = Config::fromFile(sprintf('%s/data/info_module_config.php', __DIR__));

        $this->service = new Service();
        $this->service->addModule(
            new InfoModule(
                $config
            )
        );

        $bearerAuthentication = new BasicAuthenticationHook(
            [
                'vpn-user-portal' => 'aabbcc',
            ]
        );

        $this->service->addBeforeHook('auth', $bearerAuthentication);
    }

    public function testProfileList()
    {
        $this->assertSame(
            [
                'internet' => [
                      'aclPermissionList' => [
                      ],
                      'blockLan' => false,
                      'clientToClient' => false,
                      'defaultGateway' => false,
                      'displayName' => 'Internet Access',
                      'dns' => [
                      ],
                      'enableAcl' => false,
                      'enableLog' => false,
                      'enableNat4' => false,
                      'enableNat6' => false,
                      'exposedVpnProtoPorts' => [],
                      'extIf' => 'eth0',
                      'hideProfile' => false,
                      'hostName' => 'vpn.example',
                      'listen' => '::',
                      'managementIp' => '127.0.0.1',
                      'profileNumber' => 1,
                      'range' => '10.0.0.0/24',
                      'range6' => 'fd00:4242:4242::/48',
                      'reject4' => false,
                      'reject6' => false,
                      'routes' => [
                      ],
                      'tlsProtection' => 'tls-crypt',
                      'vpnProtoPorts' => [
                        0 => 'udp/1194',
                        1 => 'tcp/1194',
                      ],
                    ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                'profile_list',
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
