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

namespace fkooman\VPN\Server;

use fkooman\Http\Exception\BadRequestException;

class InputValidation
{
    // XXX dot matches any char?
    const COMMON_NAME_PATTERN = '/^[a-zA-Z0-9-_.@]+$/';
    const USER_ID_PATTERN = '/^[a-zA-Z0-9-_.@]+$/';
    const DATE_PATTERN = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/';

    public static function commonName($commonName, $isRequired)
    {
        $commonName = !empty($commonName) ? $commonName : null;
        if (!$isRequired && is_null($commonName)) {
            return;
        }
        if (0 === preg_match(self::COMMON_NAME_PATTERN, $commonName)) {
            throw new BadRequestException('invalid value for "common_name"');
        }
        if ('..' === $commonName) {
            throw new BadRequestException('"common_name" cannot be ".."');
        }

        return $commonName;
    }

    public static function userId($userId, $isRequired)
    {
        $userId = !empty($userId) ? $userId : null;
        if (!$isRequired && is_null($userId)) {
            return;
        }
        if (0 === preg_match(self::USER_ID_PATTERN, $userId)) {
            throw new BadRequestException('invalid value for "user_id"');
        }
        if ('..' === $userId) {
            throw new BadRequestException('"user_id" cannot be ".."');
        }

        return $userId;
    }

    public static function date($date, $isRequired)
    {
        $date = !empty($date) ? $date : null;
        if (!$isRequired && is_null($date)) {
            return;
        }
        if (0 === preg_match(self::DATE_PATTERN, $date)) {
            throw new BadRequestException('invalid value for "date"');
        }

        return $date;
    }

    public static function v4($v4, $isRequired)
    {
        $v4 = !empty($v4) ? $v4 : null;
        if (!$isRequired && is_null($v4)) {
            return;
        }
        if (false === filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new BadRequestException('"v4" is invalid');
        }

        return $v4;
    }
}
