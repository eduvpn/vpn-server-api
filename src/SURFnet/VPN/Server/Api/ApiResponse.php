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

use fkooman\Http\JsonResponse;
use InvalidArgumentException;
use DomainException;

class ApiResponse extends JsonResponse
{
    public function __construct($wrapperKey, $responseData)
    {
        if (!is_string($wrapperKey)) {
            throw new InvalidArgumentException('parameter must be string');
        }
        if (0 >= strlen($wrapperKey)) {
            throw new DomainException('string must not be empty');
        }
        parent::__construct();
        $this->setBody(
            [
                'data' => [
                    $wrapperKey => $responseData,
                ],
            ]
        );
    }
}
