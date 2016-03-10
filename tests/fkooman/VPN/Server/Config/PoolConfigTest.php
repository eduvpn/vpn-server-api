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

use PHPUnit_Framework_TestCase;

class PoolConfigTest extends PHPUnit_Framework_TestCase
{
    public function testDefault()
    {
        $p = new PoolConfig(
            [
                'range' => '10.42.42.0/27',
                'name' => 'Default',
            ]
        );

        $this->assertSame('Default', $p->getName());
        $this->assertSame('10.42.42.0', $p->getRange()->getNetwork());
        $this->assertSame('255.255.255.224', $p->getRange()->getNetmask());
        $this->assertSame(['0.0.0.0/0'], $p->getDstNet4());
        $this->assertSame(['::/0'], $p->getDstNet6());
        $this->assertTrue($p->useDefaultGateway());
    }

    public function testV6()
    {
        $p = new PoolConfig(
            [
                'range' => '10.42.42.32/27',
                'name' => 'IPv6-only',
                'firewall' => [
                    'dst_net' => ['::/0'],
                ],
            ]
        );
        $this->assertSame([], $p->getDstNet4());
        $this->assertSame(['::/0'], $p->getDstNet6());
        $this->assertTrue($p->useDefaultGateway());
    }

    public function testHttps()
    {
        $p = new PoolConfig(
            [
                'range' => '10.42.42.64/27',
                'name' => 'HTTPS-only',
                'firewall' => [
                    'dst_port' => ['TCP/443'],
                ],
            ]
        );
        $this->assertTrue($p->useDefaultGateway());
        $this->asserTSame(['TCP/443'], $p->getDstPort());
    }

    public function testCustomRoutes()
    {
        $p = new PoolConfig(
            [
                'range' => '10.10.10.0/24',
                'name' => 'Foo',
                'firewall' => [
                    'dst_net' => [
                        '192.168.1.0/24',
                        '10.8.0.0/16',
                        'fd00:4242:4242:1194::/64',
                        '2001:db8::/32',
                    ],
                ],
            ]
        );
        $this->assertFalse($p->useDefaultGateway());
        $this->assertSame(
            [
                '192.168.1.0/24',
                '10.8.0.0/16',
            ],
            $p->getDstNet4()
        );
        $this->assertSame(
            [
                'fd00:4242:4242:1194::/64',
                '2001:db8::/32',
            ],
            $p->getDstNet6()
        );
    }
}
