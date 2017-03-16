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

use Otp\Otp;
use ParagonIE\ConstantTime\Encoding;
use SURFnet\VPN\Server\Exception\TotpException;

class Totp
{
    /** @var Storage */
    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function verify($userId, $totpKey, $totpSecret = null)
    {
        // for the enroll phase totpSecret is also provided, use that then
        // instead of fetching one from the DB
        if (is_null($totpSecret)) {
            if (!$this->storage->hasTotpSecret($userId)) {
                throw new TotpException('user has no TOTP secret');
            }
            $totpSecret = $this->storage->getTotpSecret($userId);
        }

        // store the attempt even before validating it, to be able to count
        // the (failed) attempts
        if (false === $this->storage->recordTotpKey($userId, $totpKey)) {
            throw new TotpException('TOTP key replay');
        }

        if (10 < $this->storage->getTotpAttemptCount($userId)) {
            throw new TotpException('too many attempts at TOTP');
        }

        $otp = new Otp();
        if (!$otp->checkTotp(Encoding::base32DecodeUpper($totpSecret), $totpKey)) {
            throw new TotpException('invalid TOTP key');
        }
    }
}
