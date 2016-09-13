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

use SURFnet\VPN\Server\Api\Exception\HttpException;

class Request
{
    public function __construct(array $serverData, array $getData, array $postData)
    {
        $this->serverData = $serverData;
        $this->getData = $getData;
        $this->postData = $postData;
    }

    public function getRequestMethod()
    {
        return $this->serverData['REQUEST_METHOD'];
    }

    public function getServerName()
    {
        return $this->serverData['SERVER_NAME'];
    }

    public function getPathInfo()
    {
        if (!array_key_exists('PATH_INFO', $this->serverData)) {
            return '/';
        }

        return $this->serverData['PATH_INFO'];
    }

    public function getUrl()
    {
        // deal with non-standard port
        // deal with non-existing request_scheme
        return sprintf(
            '%s://%s%s',
            $this->serverData['REQUEST_SCHEME'],
            $this->serverData['SERVER_NAME'],
            $this->serverData['REQUEST_URI']
        );
    }

    public function getQueryParameter($key, $defaultValue = null)
    {
        if (array_key_exists($key, $this->getData)) {
            return $this->getData[$key];
        }

        if (is_null($defaultValue)) {
            throw new HttpException(
                sprintf('missing query parameter "%s"', $key),
                400
            );
        }

        return $defaultValue;
    }

    public function getHeader($key, $defaultValue = null)
    {
        // do some header key normalization
        if (array_key_exists($key, $this->serverData)) {
            return $this->serverData[$key];
        }

        if (is_null($defaultValue)) {
            throw new HttpException(
                sprintf('missing header "%s"', $key),
                400
            );
        }

        return $defaultValue;
    }

    public function getPostParameter($key, $defaultValue = null)
    {
        if (array_key_exists($key, $this->postData)) {
            return $this->postData[$key];
        }

        if (is_null($defaultValue)) {
            throw new HttpException(
                sprintf('missing post parameter "%s"', $key),
                400
            );
        }

        return $defaultValue;
    }
}
