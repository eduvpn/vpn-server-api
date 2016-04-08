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

class IPv6Test extends PHPUnit_Framework_TestCase
{
    public function testSimple()
    {
        $i = new IPv6('fd00::0/60');
        $this->assertSame('fd00::/60', $i->getRange());
    }

    public function testSplitRange()
    {
        $i = new IPv6('fd00:4242:4242:4242::/60');
        $this->assertSame(
            [
                'fd00:4242:4242:4240::/64',
                'fd00:4242:4242:4241::/64',
            ],
            $i->splitRange(2)
        );
    }

    public function testSplitRangeTwo()
    {
        $i = new IPv6('fd00:4242:4242:42ff::/60');
        $this->assertSame(
            [
                'fd00:4242:4242:42f0::/64',
                'fd00:4242:4242:42f1::/64',
            ],
            $i->splitRange(2)
        );
    }

    public function testSplitRangeThree()
    {
        $i = new IPv6('fd00:4242:4242:1194::/60');
        $this->assertSame(
            [
                'fd00:4242:4242:1190::/64',
                'fd00:4242:4242:1191::/64',
            ],
            $i->splitRange(2)
        );
    }
}
