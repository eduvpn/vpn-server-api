<?php

/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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
        InputValidation::commonName('foo_bar');
        InputValidation::commonName('foo');
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid value for "common_name"
     */
    public function testInvalidCommonNameNull()
    {
        InputValidation::commonName(null);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid value for "common_name"
     */
    public function testInvalidCommonNameEmpty()
    {
        InputValidation::commonName('');
    }

    public function testDate()
    {
        InputValidation::dateTime('2015-01-01');
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid date/time format
     */
    public function testInvalidDate()
    {
        InputValidation::dateTime('XYZ');
    }

    public function testOtpKey()
    {
        InputValidation::otpKey('123456');
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid OTP key format
     */
    public function testOtpKeyInvalid()
    {
        // must be length 6
        InputValidation::otpKey('123');
    }

    public function testOtpSecret()
    {
        InputValidation::otpSecret('7ZCJEKXKHJVDZBWN');
    }
}
