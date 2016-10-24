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
namespace SURFnet\VPN\Server\Api;

use SURFnet\VPN\Common\Http\Exception\HttpException;

class InputValidation
{
    const PROFILE_ID_PATTERN = '/^[a-zA-Z-_]+$/';
    const COMMON_NAME_PATTERN = '/^[a-zA-Z0-9-_.@]+$/';
    const USER_ID_PATTERN = '/^[a-zA-Z0-9-_.@]+$/';
    const OTP_KEY_PATTERN = '/^[0-9]{6}$/';
    const OTP_SECRET_PATTERN = '/^[A-Z0-9]{16}$/';
    const ACCESS_TOKEN_PATTERN = '/^[\x20-\x7E]+$/';

    public static function commonName($commonName)
    {
        if (0 === preg_match(self::COMMON_NAME_PATTERN, $commonName)) {
            throw new HttpException('invalid value for "common_name"', 400);
        }
        if ('..' === $commonName) {
            throw new HttpException('"common_name" cannot be ".."', 400);
        }
    }

    public static function userId($userId)
    {
        if (0 === preg_match(self::USER_ID_PATTERN, $userId)) {
            throw new HttpException('invalid value for "user_id"', 400);
        }
        if ('..' === $userId) {
            throw new HttpException('"user_id" cannot be ".."', 400);
        }
    }

    public static function disable($disable)
    {
        if (!is_bool($disable)) {
            throw new HttpException('"disable" must be boolean', 400);
        }
    }

    public static function otpKey($otpKey)
    {
        if (0 === preg_match(self::OTP_KEY_PATTERN, $otpKey)) {
            throw new HttpException('invalid OTP key format', 400);
        }
    }

    public static function otpSecret($otpSecret)
    {
        if (0 === preg_match(self::OTP_SECRET_PATTERN, $otpSecret)) {
            throw new HttpException('invalid OTP secret format', 400);
        }
    }

    public static function vootToken($vootToken)
    {
        if (!is_string($vootToken) || 0 >= mb_strlen($vootToken)) {
            throw new HttpException('voot token must be non-empty string', 400);
        }
        if (0 === preg_match(self::ACCESS_TOKEN_PATTERN, $vootToken)) {
            throw new HttpException('invalid value for "vootToken"', 400);
        }
    }

    public static function dateTime($dateTime)
    {
        if (false === strtotime($dateTime)) {
            throw new HttpException('invalid date/time format', 400);
        }
    }

    public static function profileId($profileId)
    {
        if (0 === preg_match(self::PROFILE_ID_PATTERN, $profileId)) {
            throw new HttpException('invalid profileId format', 400);
        }
    }

    public static function ipAddress($ipAddress)
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new HttpException('invalid IP address', 400);
        }
    }

    public static function ip4($ipAddress)
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new HttpException('invalid IPv4 address', 400);
        }
    }

    public static function ip6($ipAddress)
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new HttpException('invalid IPv6 address', 400);
        }
    }

    public static function connectedAt($connectedAt)
    {
        if (!is_numeric($connectedAt) || 0 >= $connectedAt) {
            throw new HttpException('connectedAt must be positive integer', 400);
        }
    }

    public static function disconnectedAt($disconnectedAt)
    {
        if (!is_numeric($disconnectedAt) || 0 >= $disconnectedAt) {
            throw new HttpException('disconnectedAt must be positive integer', 400);
        }
    }

    public static function bytesTransferred($bytesTransferred)
    {
        if (!is_numeric($bytesTransferred) || 0 >= $bytesTransferred) {
            throw new HttpException('bytesTransferred must be positive integer', 400);
        }
    }
}
