<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
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

namespace fkooman\VPN\Server\Config;

use fkooman\VPN\Server\InputValidation;

class UserConfig
{
    /** @var bool */
    private $disable;

    /** @var string|false */
    private $otpSecret;

    public function __construct(array $configData)
    {
        $disable = array_key_exists('disable', $configData) ? $configData['disable'] : false;
        InputValidation::disable($disable);
        $this->disable = $disable;

        $otpSecret = array_key_exists('otp_secret', $configData) ? $configData['otp_secret'] : false;
        if (!is_bool($otpSecret)) {
            InputValidation::otpSecret($otpSecret);
        }
        $this->otpSecret = $otpSecret;
    }

    public function getDisable()
    {
        return $this->disable;
    }

    public function setDisable($disable)
    {
        InputValidation::disable($disable);
        $this->disable = $disable;
    }

    public function getOtpSecret()
    {
        return $this->otpSecret;
    }

    /**
     * Hide the OTP secret by setting it to 'true' if a secret is set, or leave
     * it 'false' when no OTP secret was set.
     */
    public function hideOtpSecret()
    {
        if (false !== $this->otpSecret) {
            $this->otpSecret = true;
        }
    }

    public function setOtpSecret($otpSecret)
    {
        InputValidation::otpSecret($otpSecret);
        $this->otpSecret = $otpSecret;
    }

    public function toArray()
    {
        return [
            'disable' => $this->disable,
            'otp_secret' => $this->otpSecret,
        ];
    }
}
