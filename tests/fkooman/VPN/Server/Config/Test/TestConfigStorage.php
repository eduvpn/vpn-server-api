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
use fkooman\VPN\Server\Config\UserConfig;
use fkooman\VPN\Server\Config\CommonNameConfig;

class TestConfigStorage implements ConfigStorageInterface
{
    /** @var array */
    private $commonNameConfig;

    /** @var array */
    private $userConfig;

    public function __construct()
    {
        $this->commonNameConfig = [
            'foo_bar' => new CommonNameConfig(['disable' => true]),
            'foo_baz' => new CommonNameConfig(['disable' => false]),
            'bar_foo' => new CommonNameConfig([]),
        ];

        $this->userConfig = [
            'foo' => new UserConfig([]),
        ];
    }

    public function getUserConfig($userId)
    {
        if (array_key_exists($userId, $this->userConfig)) {
            return $this->userConfig[$userId];
        }

        return new UserConfig([]);
    }

    public function setUserConfig($userId, UserConfig $userConfig)
    {
        // NOP
    }

    public function getCommonNameConfig($commonName)
    {
        if (array_key_exists($commonName, $this->commonNameConfig)) {
            return $this->commonNameConfig[$commonName];
        }

        return new CommonNameConfig([]);
    }

    public function getAllCommonNameConfig($userId)
    {
        $c = [];
        foreach ($this->commonNameConfig as $k => $v) {
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

    public function setCommonNameConfig($commonName, CommonNameConfig $commonNameConfig)
    {
        $this->commonNameConfig[$commonName] = $commonNameConfig;
    }
}
