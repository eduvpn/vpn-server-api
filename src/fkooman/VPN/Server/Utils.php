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

class Utils
{
    public static function validateCommonName($commonName)
    {
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $commonName)) {
            throw new BadRequestException('invalid characters in common name');
        }

        // MUST NOT be '..'
        if ('..' === $commonName) {
            throw new BadRequestException('common name cannot be ".."');
        }
    }

    public static function validateUserId($userId)
    {
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $userId)) {
            throw new BadRequestException('invalid characters in userId');
        }
    }

    /**
     * Validate that the date is in YYYY-MM-DD format.
     *
     * @param string $dateString the date in YYYY-MM-DD format
     */
    public static function validateDate($dateString)
    {
        if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $dateString)) {
            throw new BadRequestException('invalid date format');
        }
    }
}
