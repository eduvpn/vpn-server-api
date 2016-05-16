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

class IPTest extends PHPUnit_Framework_TestCase
{
    public function testWithPrefix4()
    {
        $ip = new IP('192.168.1.5/24');
        $this->assertSame('192.168.1.5', $ip->getAddress());
        $this->assertSame(24, $ip->getPrefix());
        $this->assertSame('192.168.1.5/24', $ip->getAddressPrefix());
        $this->assertSame('192.168.1.5/24', $ip->__toString());
        $this->assertSame('255.255.255.0', $ip->getNetmask());
        $this->assertSame('192.168.1.0', $ip->getNetwork());
        $this->assertSame(254, $ip->getNumberOfHosts());
        $this->assertSame(4, $ip->getFamily());
    }

    public function testWithoutPrefix4()
    {
        $ip = new IP('192.168.1.5');
        $this->assertSame('192.168.1.5', $ip->getAddress());
        $this->assertSame(32, $ip->getPrefix());
        $this->assertSame('192.168.1.5/32', $ip->getAddressPrefix());
        $this->assertSame('255.255.255.255', $ip->getNetmask());
        $this->assertSame('192.168.1.5', $ip->getNetwork());
        $this->assertSame(-1, $ip->getNumberOfHosts());
        $this->assertSame(4, $ip->getFamily());
    }

    public function testValid4()
    {
        $ip = new IP('0.0.0.0/0');
        $ip = new IP('0.0.0.0');
        $ip = new IP('127.0.0.1');
        $ip = new IP('10.10.0.0/16');
    }

    /**
     * @expectedException \fkooman\VPN\Server\IPException
     * @expectedExceptionMessage invalid IP address
     */
    public function testInvalidAddress4()
    {
        $ip = new IP('192.168.1.256/24');
    }

    /**
     * @expectedException \fkooman\VPN\Server\IPException
     * @expectedExceptionMessage invalid IP address
     */
    public function testInvalidAddress6()
    {
        $ip = new IP('fd00::1::/64');
    }

    /**
     * @expectedException \fkooman\VPN\Server\IPException
     * @expectedExceptionMessage IP prefix must be a number between 0 and 32
     */
    public function testInvalidPrefix4()
    {
        $ip = new IP('192.168.1.5/33');
    }

    /**
     * @expectedException \fkooman\VPN\Server\IPException
     * @expectedExceptionMessage IP prefix must be a number between 0 and 128
     */
    public function testInvalidPrefix6()
    {
        $ip = new IP('fd00::1/130');
    }

    public function testWithPrefix6()
    {
        $ip = new IP('fd00:1234:1234:1234:aabb:ccdd:eeee:ffff/64');
        $this->assertSame('fd00:1234:1234:1234:aabb:ccdd:eeee:ffff', $ip->getAddress());
        $this->assertSame(64, $ip->getPrefix());
        $this->assertSame('fd00:1234:1234:1234:aabb:ccdd:eeee:ffff/64', $ip->getAddressPrefix());
        $this->assertSame(6, $ip->getFamily());
    }

    public function testWithoutPrefix6()
    {
        $ip = new IP('fd00:1234:1234:1234:aabb:ccdd:eeee:ffff');
        $this->assertSame('fd00:1234:1234:1234:aabb:ccdd:eeee:ffff', $ip->getAddress());
        $this->assertSame(128, $ip->getPrefix());
        $this->assertSame('fd00:1234:1234:1234:aabb:ccdd:eeee:ffff/128', $ip->getAddressPrefix());
        $this->assertSame(6, $ip->getFamily());
    }

    public function testSplit4()
    {
        $ip = new IP('192.168.1.0/24');
        $this->assertEquals(
            [
                new IP('192.168.1.0/27'),
                new IP('192.168.1.32/27'),
                new IP('192.168.1.64/27'),
                new IP('192.168.1.96/27'),
                new IP('192.168.1.128/27'),
                new IP('192.168.1.160/27'),
                new IP('192.168.1.192/27'),
                new IP('192.168.1.224/27'),
            ],
            $ip->split(8)
        );
    }

    public function testSplit6()
    {
        $ip = new IP('fd00:1234:1234::/48');
        $this->assertEquals(
            [
                new IP('fd00:1234:1234::/64'),
                new IP('fd00:1234:1234:1::/64'),
                new IP('fd00:1234:1234:2::/64'),
                new IP('fd00:1234:1234:3::/64'),
                new IP('fd00:1234:1234:4::/64'),
                new IP('fd00:1234:1234:5::/64'),
                new IP('fd00:1234:1234:6::/64'),
                new IP('fd00:1234:1234:7::/64'),
            ],
            $ip->split(8)
        );
    }

#    public function test24()
#    {
#        $i = new IP('10.42.42.0/24');
#        $this->assertSame('255.255.255.0', $i->getNetmask());
#        $this->assertSame('10.42.42.0', $i->getNetwork());
#    }

#    public function test25()
#    {
#        $i = new IP('10.42.42.0/25');
#        $this->assertSame('255.255.255.128', $i->getNetmask());
#        $this->assertSame('10.42.42.0', $i->getNetwork());
#    }

#    public function test23()
#    {
#        $i = new IP('10.42.42.0/23');
#        $this->assertSame('255.255.254.0', $i->getNetmask());
#        $this->assertSame('10.42.42.0', $i->getNetwork());
#    }

#    public function test32()
#    {
#        $i = new IP('10.42.42.42/32');
#        $this->assertSame('255.255.255.255', $i->getNetmask());
#        $this->assertSame('10.42.42.42', $i->getNetwork());
#    }

#    public function testNonNullStart()
#    {
#        $i = new IP('10.42.43.12/23');
#        $this->assertSame('255.255.254.0', $i->getNetmask());
#        $this->assertSame('10.42.42.0', $i->getNetwork());
#    }

#    /**
#     * @expectedException \fkooman\VPN\Server\IPException
#     * @expectedExceptionMessage invalid IP address
#     */
#    public function testInvalidIP()
#    {
#        $i = new IP('10.42.42.260/24');
#    }

#    /**
#     * @expectedException \fkooman\VPN\Server\IPException
#     * @expectedExceptionMessage IP prefix must be a number between 0 and 32
#     */
#    public function testInvalidPrefix()
#    {
#        $i = new IP('10.42.42.0/40');
#    }

#    public function testNotCidr()
#    {
#        $i = new IP('10.42.42.0');
#    }

#    /**
#     * @expectedException \fkooman\VPN\Server\IPException
#     * @expectedExceptionMessage IP prefix must be a number between 0 and 32
#     */
#    public function testNotValidCidr()
#    {
#        $i = new IP('10.42.42.0//24');
#    }

#    public function testNumberOfHosts()
#    {
#        $i = new IP('10.42.42.0/24');
#        $this->assertEquals(254, $i->getNumberOfHosts());
#        $i = new IP('10.42.42.0/25');
#        $this->assertEquals(126, $i->getNumberOfHosts());
#    }

#    public function testSplitRange()
#    {
#        $i = new IP('10.42.42.0/24');
#        $this->assertEquals(['10.42.42.0/24'], $i->split(1));

#        $i = new IP('10.42.42.0/24');
#        $this->assertEquals(['10.42.42.0/25', '10.42.42.128/25'], $i->split(2));

#        $i = new IP('10.42.42.0/27');
#        $this->assertEquals(['10.42.42.0/28', '10.42.42.16/28'], $i->split(2));

#        $i = new IP('10.42.42.0/24');
#        $this->assertEquals(['10.42.42.0/26', '10.42.42.64/26', '10.42.42.128/26', '10.42.42.192/26'], $i->split(4));

#        $i = new IP('10.42.42.0/25');
#        $this->assertEquals(['10.42.42.0/26', '10.42.42.64/26'], $i->split(2));

#        $i = new IP('10.42.42.0/26');
#        $this->assertEquals(['10.42.42.0/28', '10.42.42.16/28', '10.42.42.32/28', '10.42.42.48/28'], $i->split(4));
#    }

#    public function testSimple6()
#    {
#        $i = new IP('fd00::0/60');
#        $this->assertEquals('fd00::/60', $i->__toString());
#    }

#    public function testSplit6()
#    {
#        $i = new IP('fd00:4242:4242:4242::/60');
#        $this->assertEquals(
#            [
#                'fd00:4242:4242:4240::/64',
#                'fd00:4242:4242:4241::/64',
#            ],
#            $i->split(2)
#        );
#    }

#    public function testSplitRangeTwo6()
#    {
#        $i = new IP('fd00:4242:4242:42ff::/60');
#        $this->assertEquals(
#            [
#                'fd00:4242:4242:42f0::/64',
#                'fd00:4242:4242:42f1::/64',
#            ],
#            $i->split(2)
#        );
#    }
}
