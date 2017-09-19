<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
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
        if (null === $totpSecret) {
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

        if (60 < $this->storage->getTotpAttemptCount($userId)) {
            throw new TotpException('too many attempts at TOTP');
        }

        $otp = new Otp();
        if (!$otp->checkTotp(Encoding::base32DecodeUpper($totpSecret), $totpKey)) {
            throw new TotpException('invalid TOTP key');
        }
    }
}
