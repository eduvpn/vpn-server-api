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

namespace fkooman\VPN\Server\Config;

require_once __DIR__.'/Test/TestConfigStorage.php';

use fkooman\VPN\Server\Config\Test\TestConfigStorage;
use PHPUnit_Framework_TestCase;

class ClientConfigTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\VPN\Server\Config\ClientConfig */
    private $clientConfig;

    public function setUp()
    {
        $this->clientConfig = new ClientConfig(
            [
                'leaseDir' => sys_get_temp_dir(),
                'v4' => [
                    'dns' => ['9.9.9.9', '1.1.1.1'],
                    'range' => '10.42.42.0/24',
                    'pools' => [
                        'v6' => [
                            'name' => 'IPv6-only',
                            'firewall' => [
                                'dst_net' => ['::/0'],
                            ],
                            'range' => '10.42.42.128/25',
                        ],
                        'admin' => [
                            'name' => 'Admin',
                            'range' => '10.42.42.0/25',
                            'firewall' => [
                                'dst_net' => [
                                    '192.168.42.0/24',
                                    'fd00:42:42:42::/64',
                                ],
                            ],
                        ],
                    ],
                ],
                'v6' => [
                    'prefix' => 'fd00:4242:4242:1194',
                    'dns' => ['fd00:53:53:53:53:53:53:53'],
                ],
            ],
            new TestConfigStorage()
        );
    }

    public function testGenerateFooBar()
    {
        $this->assertSame(
            [
                'v4' => '10.42.42.129',
                'v4_netmask' => '255.255.255.0',
                'v4_gw' => '10.42.42.1',
                'v6' => 'fd00:4242:4242:1194::ffff:0a2a:2a81',
                'v6_gw' => 'fd00:4242:4242:1194::ffff:0a2a:2a01',
                'dns' => [
                    '9.9.9.9',
                    '1.1.1.1',
                    'fd00:53:53:53:53:53:53:53',
                ],
                'default_gw' => true,
                'dst_net4' => [],
                'dst_net6' => [
                    '::/0',
                ],
            ],
            $this->clientConfig->get('foo_bar')
        );
    }

    public function testGenerateAdminXyz()
    {
        $this->assertSame(
            [
                'v4' => '10.42.42.2',
                'v4_netmask' => '255.255.255.0',
                'v4_gw' => '10.42.42.1',
                'v6' => 'fd00:4242:4242:1194::ffff:0a2a:2a02',
                'v6_gw' => 'fd00:4242:4242:1194::ffff:0a2a:2a01',
                'dns' => [
                    '9.9.9.9',
                    '1.1.1.1',
                    'fd00:53:53:53:53:53:53:53',
                ],
                'default_gw' => false,
                'dst_net4' => [
                    '192.168.42.0/24',
                ],
                'dst_net6' => [
                    'fd00:42:42:42::/64',
                ],
            ],
            $this->clientConfig->get('admin_xyz')
        );
    }
}
