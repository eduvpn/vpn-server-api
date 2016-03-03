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

class AddressPoolTest extends PHPUnit_Framework_TestCase
{
    public function testGetIp4()
    {
        $this->assertSame('10.10.10.1', AddressPool::getIp4('10.10.10.1', '10.10.10.10', []));
        $this->assertFalse(AddressPool::getIp4('10.10.10.1', '10.10.10.1', []));
    }

    public function testGetIp4Used()
    {
        $this->assertSame('10.10.10.2', AddressPool::getIp4('10.10.10.1', '10.10.10.10', ['10.10.10.1']));
        $this->assertFalse(AddressPool::getIp4('10.10.10.1', '10.10.10.2', ['10.10.10.1']));

        $this->assertSame(
            '10.10.10.4',
            AddressPool::getIp4('10.10.10.1', '10.10.10.10', ['10.10.10.1', '10.10.10.2', '10.10.10.3'])
        );
    }
}
