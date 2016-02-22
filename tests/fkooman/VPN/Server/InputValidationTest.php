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

class InputValidationTest extends PHPUnit_Framework_TestCase
{
    public function testCommonName()
    {
        InputValidation::commonName('foo_bar', true);
        InputValidation::commonName(null, false);
        InputValidation::commonName('', false);
        InputValidation::commonName('foo', false);
        InputValidation::commonName('foo', true);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid value for "common_name"
     */
    public function testInvalidCommonNameNull()
    {
        InputValidation::commonName(null, true);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid value for "common_name"
     */
    public function testInvalidCommonNameEmpty()
    {
        InputValidation::commonName('', true);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid value for "common_name"
     */
    public function testInvalidCommonNameSymbol()
    {
        InputValidation::commonName('!', false);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage "common_name" cannot be ".."
     */
    public function testInvalidCommonNameDots()
    {
        InputValidation::commonName('..', false);
    }

    public function testDate()
    {
        InputValidation::date('2015-01-01', true);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid value for "date"
     */
    public function testInvalidDate()
    {
        InputValidation::date('a2015-01-01', true);
    }

    public function testIp()
    {
        InputValidation::v4('10.42.42.10', true);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage "v4" is invalid
     */
    public function testInvalidIp()
    {
        InputValidation::v4('10.42.42.', true);
    }

}
