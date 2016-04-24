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

namespace fkooman\VPN\Server;

use PHPUnit_Framework_TestCase;

class IPv4Test extends PHPUnit_Framework_TestCase
{
    public function test24()
    {
        $i = new IPv4('10.42.42.0/24');
        $this->assertSame('255.255.255.0', $i->getNetmask());
        $this->assertSame('10.42.42.0', $i->getNetwork());
        $this->assertSame('10.42.42.255', $i->getBroadcast());
    }

    public function test25()
    {
        $i = new IPv4('10.42.42.0/25');
        $this->assertSame('255.255.255.128', $i->getNetmask());
        $this->assertSame('10.42.42.0', $i->getNetwork());
        $this->assertSame('10.42.42.127', $i->getBroadcast());
    }

    public function test23()
    {
        $i = new IPv4('10.42.42.0/23');
        $this->assertSame('255.255.254.0', $i->getNetmask());
        $this->assertSame('10.42.42.0', $i->getNetwork());
        $this->assertSame('10.42.43.255', $i->getBroadcast());
    }

    public function test32()
    {
        $i = new IPv4('10.42.42.42/32');
        $this->assertSame('255.255.255.255', $i->getNetmask());
        $this->assertSame('10.42.42.42', $i->getNetwork());
        $this->assertSame('10.42.42.42', $i->getBroadcast());
    }

    public function testNonNullStart()
    {
        $i = new IPv4('10.42.43.12/23');
        $this->assertSame('255.255.254.0', $i->getNetmask());
        $this->assertSame('10.42.42.0', $i->getNetwork());
        $this->assertSame('10.42.43.255', $i->getBroadcast());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage invalid IP address
     */
    public function testInvalidIP()
    {
        $i = new IPv4('10.42.42.260/24');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage invalid prefix
     */
    public function testInvalidPrefix()
    {
        $i = new IPv4('10.42.42.0/40');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage not in CIDR format
     */
    public function testNotCidr()
    {
        $i = new IPv4('10.42.42.0');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage not in CIDR format
     */
    public function testNotValidCidr()
    {
        $i = new IPv4('10.42.42.0//24');
    }

    public function testRange()
    {
        $i = new IPv4('10.42.42.0/24');
        $this->assertTrue($i->inRange('10.42.42.25'));
        $this->assertTrue($i->inRange('10.42.42.1'));
        $this->assertTrue($i->inRange('10.42.42.254'));
        $this->assertFalse($i->inRange('10.42.42.0'));
        $this->assertFalse($i->inRange('10.42.42.255'));
    }

    public function testRange25()
    {
        $i = new IPv4('10.42.42.0/25');
        $this->assertTrue($i->inRange('10.42.42.25'));
        $this->assertTrue($i->inRange('10.42.42.1'));
        $this->assertTrue($i->inRange('10.42.42.126'));
        $this->assertFalse($i->inRange('10.42.42.0'));
        $this->assertFalse($i->inRange('10.42.42.127'));
    }

    public function testRange23()
    {
        $i = new IPv4('10.42.42.199/23');
        $this->assertTrue($i->inRange('10.42.42.25'));
        $this->assertTrue($i->inRange('10.42.42.1'));
        $this->assertTrue($i->inRange('10.42.42.126'));
        $this->assertTrue($i->inRange('10.42.43.254'));
        $this->assertFalse($i->inRange('10.42.42.0'));
        $this->assertFalse($i->inRange('10.42.43.255'));
    }

    public function testGetFirstLastHost()
    {
        $i = new IPv4('10.42.42.0/25');
        $this->assertSame('10.42.42.1', $i->getFirstHost());
        $this->assertSame('10.42.42.126', $i->getLastHost());
    }

    public function testInRangeNetworkBroadcast()
    {
        $i = new IPv4('10.42.42.128/25');
        $this->assertTrue($i->inRange('10.42.42.128', true));
        $this->assertTrue($i->inRange('10.42.42.255', true));
        $this->assertFalse($i->inRange('10.42.42.128', false));
        $this->assertFalse($i->inRange('10.42.42.255', false));
    }

    public function testNumberOfHosts()
    {
        $i = new IPv4('10.42.42.0/24');
        $this->assertEquals(254, $i->getNumberOfHosts());
        $i = new IPv4('10.42.42.0/25');
        $this->assertEquals(126, $i->getNumberOfHosts());
    }

    public function testSplitRange()
    {
        $i = new IPv4('10.42.42.0/24');
        $this->assertSame(['10.42.42.0/24'], $i->splitRange(1));

        $i = new IPv4('10.42.42.0/24');
        $this->assertSame(['10.42.42.0/25', '10.42.42.128/25'], $i->splitRange(2));

        $i = new IPv4('10.42.42.0/27');
        $this->assertSame(['10.42.42.0/28', '10.42.42.16/28'], $i->splitRange(2));

        $i = new IPv4('10.42.42.0/24');
        $this->assertSame(['10.42.42.0/26', '10.42.42.64/26', '10.42.42.128/26'], $i->splitRange(3));

        $i = new IPv4('10.42.42.0/24');
        $this->assertSame(['10.42.42.0/26', '10.42.42.64/26', '10.42.42.128/26', '10.42.42.192/26'], $i->splitRange(4));

        $i = new IPv4('10.42.42.0/25');
        $this->assertSame(['10.42.42.0/26', '10.42.42.64/26'], $i->splitRange(2));

        $i = new IPv4('10.42.42.0/26');
        $this->assertSame(['10.42.42.0/28', '10.42.42.16/28', '10.42.42.32/28', '10.42.42.48/28'], $i->splitRange(4));
    }
}
