<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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
