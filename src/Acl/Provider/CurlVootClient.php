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

namespace SURFnet\VPN\Server\Acl\Provider;

use RuntimeException;

class CurlVootClient implements VootClientInterface
{
    /** @var resource */
    private $curlChannel;

    public function __construct()
    {
        if (false === $this->curlChannel = curl_init()) {
            throw new RuntimeException('unable to create cURL channel');
        }
    }

    public function __destruct()
    {
        curl_close($this->curlChannel);
    }

    public function get($requestUri, $bearerToken)
    {
        $curlOptions = [
            CURLOPT_URL => $requestUri,
//            CURLOPT_USERPWD => sprintf('%s:%s', $this->authInfo[0], $this->authInfo[1]),
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ];

        if (false === curl_setopt_array($this->curlChannel, $curlOptions)) {
            throw new RuntimeException('unable to set cURL options');
        }

        if (false === $responseData = curl_exec($this->curlChannel)) {
            throw new RuntimeException('failure performing the HTTP request');
        }

        return [
            curl_getinfo($this->curlChannel, CURLINFO_HTTP_CODE),
            json_decode($responseData, true),
        ];
    }
}
