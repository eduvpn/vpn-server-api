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
