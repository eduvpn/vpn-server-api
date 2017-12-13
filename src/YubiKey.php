<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server;

use fkooman\YubiTwee\CurlMultiHttpClient;
use fkooman\YubiTwee\Exception\YubiTweeException;
use fkooman\YubiTwee\Validator;
use SURFnet\VPN\Server\Exception\YubiKeyException;

class YubiKey
{
    /**
     * @param string $yubiKeyOtp
     *
     * @return string
     */
    public function getVerifiedId($yubiKeyOtp)
    {
        try {
            $validator = new Validator(new CurlMultiHttpClient());
            $response = $validator->verify($yubiKeyOtp);

            if (!$response->success()) {
                throw new YubiKeyException(sprintf('YubiKey OTP is not valid: %s', $response->status()));
            }

            return $response->id();
        } catch (YubiTweeException $e) {
            throw new YubiKeyException(sprintf('YubiKey OTP validation failed: %s', $e->getMessage()));
        }
    }

    /**
     * @param string $yubiKeyOtp
     * @param string $expectedYubiKeyId
     *
     * @return void
     */
    public function verifyOtpForId($yubiKeyOtp, $expectedYubiKeyId)
    {
        if ($expectedYubiKeyId !== $this->getVerifiedId($yubiKeyOtp)) {
            throw new YubiKeyException('Used YubiKey does not match expected YubiKey for user');
        }
    }
}
