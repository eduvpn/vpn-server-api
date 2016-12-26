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

use DateTime;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Server\Exception\TwoFactorException;

class TwoFactorTest extends PHPUnit_Framework_TestCase
{
    /** @var TwoFactor */
    private $twoFactor;

    public function setUp()
    {
        $storage = new Storage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            new DateTime()
        );
        $storage->init();
        $storage->setTotpSecret('foo', 'CN2XAL23SIFTDFXZ');
        $this->twoFactor = new TwoFactor($storage);
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\TwoFactorException
     * @expectedExceptionMessage too many attempts at TOTP
     */
    public function testTooManyReplays()
    {
        for ($i = 0; $i < 10; ++$i) {
            try {
                $this->twoFactor->verifyTotp('foo', (string) 123456 + $i);
            } catch (TwoFactorException $e) {
                $this->assertSame('invalid TOTP key', $e->getMessage());
            }
        }
        $this->twoFactor->verifyTotp('foo', '555555');
    }
}
