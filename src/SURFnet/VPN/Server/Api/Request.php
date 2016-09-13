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
    /** @var array */
    private $serverData;

    /** @var array */
    private $getData;

    /** @var array */
    private $postData;

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

    public function getQueryParameter($key, $isRequired = true, $defaultValue = null)
    {
        return self::getValueFromArray('query parameter', $this->getData, $key, $isRequired, $defaultValue);
    }

    public function getPostParameter($key, $isRequired = true, $defaultValue = null)
    {
        return self::getValueFromArray('post parameter', $this->postData, $key, $isRequired, $defaultValue);
    }

    public function getHeader($key, $isRequired = true, $defaultValue = null)
    {
        return self::getValueFromArray('header', $this->serverData, $key, $isRequired, $defaultValue);
    }

    private static function getValueFromArray($type, array $sourceData, $key, $isRequired, $defaultValue)
    {
        if (array_key_exists($key, $sourceData)) {
            return $sourceData[$key];
        }

        if ($isRequired) {
            throw new HttpException(
                sprintf('missing required %s "%s"', $type, $key),
                400
            );
        }

        return $defaultValue;
    }
}
