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

use SURFnet\VPN\Server\Exception\ConfigException;

/**
 * Read configuration.
 */
class Config
{
    /** @var array */
    protected $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    public static function fromFile($configFile)
    {
        // XXX handle unreadable file
        $parsedConfig = @yaml_parse_file($configFile);

        if (!is_array($parsedConfig)) {
            throw new ConfigException(sprintf('invalid configuration file format in "%s"', $configFile));
        }

        return new static($parsedConfig);
    }

    public function s($key, $value)
    {
        $this->configData[$key] = $value;
    }

    public function v($key, $defaultValue = null)
    {
        if (array_key_exists($key, $this->configData)) {
            return $this->configData[$key];
        }

        if (is_null($defaultValue)) {
            throw new ConfigException(sprintf('missing configuration field "%s"', $key));
        }

        return $defaultValue;
    }

    public function toArray()
    {
        return $this->configData;
    }
}
