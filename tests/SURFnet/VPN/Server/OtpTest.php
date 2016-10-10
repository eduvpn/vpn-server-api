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
namespace SURFnet\VPN\Server;

require_once sprintf('%s/Test/TestHttpClient.php', __DIR__);

use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use Psr\Log\NullLogger;
use SURFnet\VPN\Server\Test\TestHttpClient;

class OtpTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
    }

    public function testValidOtp()
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');

        $otp = new Otp(new NullLogger(), $serverClient);
        $this->assertTrue(
            $otp->verify(
                [
                    'username' => 'totp',
                    'common_name' => 'foo_bar',
                    'password' => '123456',
                ]
            )
        );
    }

    public function testNoOtpSecret()
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');

        $otp = new Otp(new NullLogger(), $serverClient);
        $this->assertFalse(
            $otp->verify(
                [
                    'username' => 'totp',
                    'common_name' => 'bar_foo',
                    'password' => '123456',
                ]
            )
        );
    }

    public function testNoInvalidOtpKey()
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');

        $otp = new Otp(new NullLogger(), $serverClient);
        $this->assertFalse(
            $otp->verify(
                [
                    'username' => 'totp',
                    'common_name' => 'foo_bar',
                    'password' => '654321',
                ]
            )
        );
    }

    public function testInvalidOtpPattern()
    {
        $serverClient = new ServerClient(new TestHttpClient(), 'serverClient');

        $otp = new Otp(new NullLogger(), $serverClient);
        $this->assertFalse(
            $otp->verify(
                [
                    'username' => 'totp',
                    'common_name' => 'foo_bar',
                    'password' => '123',
                ]
            )
        );
    }
}
