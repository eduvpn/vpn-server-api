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

class BearerValidatorTest extends PHPUnit_Framework_TestCase
{
    public function testValid()
    {
        $v = new BearerValidator([
            '12345678',
            '87654321',
        ]);
        $this->assertTrue($v->validate('87654321')->get('active'));
    }

    public function testInvalid()
    {
        $v = new BearerValidator([
            '12345678',
            '87654321',
        ]);
        $this->assertFalse($v->validate('44445555')->get('active'));
    }
}
