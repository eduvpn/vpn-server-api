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

use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Server\Acl\Provider\StaticProvider;

class InfoModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \SURFnet\VPN\Common\Http\Service */
    private $service;

    public function setUp()
    {
        $config = Config::fromFile(sprintf('%s/data/info_module_config.yaml', __DIR__));

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
                'data' => [
                    'profile_list' => [
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
                            'managementIp' => 'auto',
                            'blockSmb' => false,
                            'reject4' => false,
                            'reject6' => false,
                            'processCount' => 4,
                            'aclGroupProvider' => 'StaticProvider',
                            'portShare' => true,
                            'hideProfile' => false,
                            'profileNumber' => 1,
                            'displayName' => 'Internet Access',
                            'extIf' => 'eth0',
                            'range' => '10.0.0.0/24',
                            'range6' => 'fd00:4242:4242::/48',
                            'hostName' => 'vpn.example',
                        ],
                    ],
                ],
            ],
            $this->makeRequest(
                ['vpn-user-portal', 'aabbcc'],
                'GET',
                '/profile_list',
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
                    'PATH_INFO' => $pathInfo,
                    'REQUEST_URI' => $pathInfo,
                    'PHP_AUTH_USER' => $basicAuth[0],
                    'PHP_AUTH_PW' => $basicAuth[1],
                ],
                $getData,
                $postData
            )
        );

        return json_decode($response->getBody(), true);
    }
}