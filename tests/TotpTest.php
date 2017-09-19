<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests;

use DateTime;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Server\Exception\TotpException;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\Totp;

class TotpTest extends PHPUnit_Framework_TestCase
{
    /** @var Totp */
    private $totp;

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

        $this->totp = new Totp($storage);
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\TotpException
     * @expectedExceptionMessage too many attempts at TOTP
     */
    public function testTooManyReplays()
    {
        for ($i = 0; $i < 60; ++$i) {
            try {
                $this->totp->verify('foo', (string) 123456 + $i);
            } catch (TotpException $e) {
                $this->assertSame('invalid TOTP key', $e->getMessage());
            }
        }
        $this->totp->verify('foo', '555555');
    }
}
