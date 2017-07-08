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
    public function verify($userId, $yubiKeyOtp, $yubiKeyId = null)
    {
        try {
            $validator = new Validator(new CurlMultiHttpClient());
            $response = $validator->verify($yubiKeyOtp);

            if ($response->success()) {
                if (!is_null($yubiKeyId)) {
                    // the yubiKeyId MUST match the Id from the validation
                    // response
                    if ($yubiKeyId !== $response->id()) {
                        throw new YubiKeyException('user not bound to this YubiKey ID');
                    }
                }

                return $response->id();
            }

            throw new YubiKeyException(sprintf('YubiKey OTP is not valid: %s', $response->status()));
        } catch (YubiTweeException $e) {
            throw new YubiKeyException(sprintf('YubiKey OTP validation failed: %s', $e->getMessage()));
        }
    }
}
