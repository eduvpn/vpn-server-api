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

use PHPUnit_Framework_TestCase;
use Otp\Otp;
use Base32\Base32;
use PDO;

class TwoFactorTest extends PHPUnit_Framework_TestCase
{
    /** @var OtpLog */
    private $otpLog;

    public function setUp()
    {
        $db = new PDO('sqlite::memory:');
        $this->otpLog = new OtpLog($db);
        $this->otpLog->initDatabase();
    }

    public function testTwoFactorValid()
    {
        $o = new Otp();
        $otpKey = $o->totp(Base32::decode('QPXDFE7G7VNRR4BH'));

        $c = new TwoFactor(__DIR__, $this->otpLog);
        $c->twoFactor(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'foo_xyz',
                'username' => 'totp',
                'password' => $otpKey,
            ]
        );
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\TwoFactorException
     * @expectedExceptionMessage invalid OTP key
     */
    public function testTwoFactorWrongKey()
    {
        $c = new TwoFactor(__DIR__, $this->otpLog);
        $c->twoFactor(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'foo_xyz',
                'username' => 'totp',
                'password' => '999999',
            ]
        );
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\TwoFactorException
     * @expectedExceptionMessage OTP replayed
     */
    public function testTwoFactorReplay()
    {
        $o = new Otp();
        $otpKey = $o->totp(Base32::decode('QPXDFE7G7VNRR4BH'));

        $c = new TwoFactor(__DIR__, $this->otpLog);
        $c->twoFactor(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'foo_xyz',
                'username' => 'totp',
                'password' => $otpKey,
            ]
        );
        // replay
        $c->twoFactor(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'foo_xyz',
                'username' => 'totp',
                'password' => $otpKey,
            ]
        );
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\TwoFactorException
     * @expectedExceptionMessage no OTP secret registered
     */
    public function testTwoFactorNotEnrolled()
    {
        $c = new TwoFactor(__DIR__, $this->otpLog);
        $c->twoFactor(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'bar_xyz',
                'username' => 'totp',
                'password' => '999999',
            ]
        );
    }
}
