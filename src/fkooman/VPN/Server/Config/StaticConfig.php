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

namespace fkooman\VPN\Server\Config;

use fkooman\Json\Json;
use RuntimeException;

class StaticConfig
{
    /** @var string */
    private $staticConfigDir;

    public function __construct($staticConfigDir)
    {
        $this->staticConfigDir = $staticConfigDir;
    }

    public function getConfig($commonName)
    {
        $commonNamePath = sprintf('%s/%s', $this->staticConfigDir, $commonName);
        try {
            return new Config(Json::decodeFile($commonNamePath));
        } catch (RuntimeException $e) {
            return new Config([]);
        }
    }

    public function getAllConfig($userId)
    {
        $configArray = [];
        $pathFilter = sprintf('%s/*', $this->staticConfigDir);
        if (!is_null($userId)) {
            $pathFilter = sprintf('%s/%s_*', $this->staticConfigDir, $userId);
        }
        foreach (glob($pathFilter) as $commonNamePath) {
            $commonName = basename($commonNamePath);
            $configArray[$commonName] = $this->getConfig($commonName)->toArray();
        }

        return $configArray;
    }

    public function setConfig($commonName, Config $config)
    {
        $commonNamePath = sprintf('%s/%s', $this->staticConfigDir, $commonName);

        if (false === @file_put_contents($commonNamePath, Json::encode($config->toArray()))) {
            throw new RuntimeException('unable to write to static configuration file');
        }
    }
}
