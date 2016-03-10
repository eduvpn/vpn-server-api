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

namespace fkooman\VPN\Server\Config\Test;

use fkooman\VPN\Server\Config\ConfigStorageInterface;
use fkooman\VPN\Server\Config\ConfigData;

class TestConfigStorage implements ConfigStorageInterface
{
    /** @var array */
    private $configData;

    public function __construct()
    {
        $this->configData = [
            'foo_bar' => new ConfigData(['pool' => 'v6']),
            'bar_foo' => new ConfigData(['disable' => true]),
            'admin_xyz' => new ConfigData(['pool' => 'admin']),
        ];
    }

    public function getConfig($commonName)
    {
        if (array_key_exists($commonName, $this->configData)) {
            return $this->configData[$commonName];
        }

        return new ConfigData([]);
    }

    public function getAllConfig($userId)
    {
        $c = [];
        foreach ($this->configData as $k => $v) {
            if (is_null($userId)) {
                $c[$k] = $v->toArray();
            } else {
                if (0 === strpos($k, $userId.'_')) {
                    $c[$k] = $v->toArray();
                }
            }
        }

        return $c;
    }

    public function setConfig($commonName, ConfigData $configData)
    {
        $this->configData[$commonName] = $configData;
    }
}
