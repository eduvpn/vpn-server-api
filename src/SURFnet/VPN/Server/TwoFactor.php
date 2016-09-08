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

use Base32\Base32;
use Otp\Otp;
use RuntimeException;
use SURFnet\VPN\Server\Exception\TwoFactorException;

class TwoFactor
{
    /** @var string */
    private $baseDir;

    /** @var OtpLog */
    private $otpLog;

    public function __construct($baseDir, OtpLog $otpLog)
    {
        $this->baseDir = $baseDir;
        $this->otpLog = $otpLog;
    }

    public function twoFactor(array $envData)
    {
        $userId = self::getUserId($envData['common_name']);

        // use username field to specify OTP type, for now we only support 'totp'
        $otpType = $envData['username'];
        if ('totp' !== $otpType) {
            throw new TwoFactorException('invalid OTP type specified in username field');
        }

        $otpKey = $envData['password'];
        // validate the OTP key
        if (0 === preg_match('/^[0-9]{6}$/', $otpKey)) {
            throw new TwoFactorException('invalid OTP key format specified');
        }

        $dataDir = sprintf('%s/data/%s', $this->baseDir, $envData['INSTANCE_ID']);

        if (false === $otpSecret = @file_get_contents(sprintf('%s/users/otp_secrets/%s', $dataDir, $userId))) {
            throw new TwoFactorException('no OTP secret registered');
        }

        $otp = new Otp();
        if ($otp->checkTotp(Base32::decode($otpSecret), $otpKey)) {
            if (false === $this->otpLog->record($userId, $otpKey, time())) {
                throw new TwoFactorException('OTP replayed');
            }
        } else {
            throw new TwoFactorException('invalid OTP key');
        }
    }

    private static function getUserId($commonName)
    {
        if (false === $uPos = strpos($commonName, '_')) {
            throw new RuntimeException('unable to extract userId from commonName');
        }

        return substr($commonName, 0, $uPos);
    }
}
