<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests\Api;

use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Api\InfoModule;

class InfoModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
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
                    'defaultGateway' => false,
                    'routes' => [],
                    'dns' => [],
                    'useNat' => false,
                    'twoFactor' => false,
                    'clientToClient' => false,
                    'listen' => '::',
                    'enableLog' => false,
                    'enableAcl' => false,
                    'aclGroupList' => [],
                    'managementIp' => '127.0.0.1',
                    'blockSmb' => false,
                    'reject4' => false,
                    'reject6' => false,
                    'vpnProtoPorts' => [
                        'udp/1194',
                        'tcp/1194',
                    ],
                    'hideProfile' => false,
                    'tlsCrypt' => false,
                    'profileNumber' => 1,
                    'displayName' => 'Internet Access',
                    'extIf' => 'eth0',
                    'range' => '10.0.0.0/24',
                    'range6' => 'fd00:4242:4242::/48',
                    'hostName' => 'vpn.example',
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
