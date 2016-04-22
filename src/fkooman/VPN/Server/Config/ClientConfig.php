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

/**
 * This class generates a configuration file specific for the connecting
 * client CN.
 */
class ClientConfig
{
    /** @var ConfigStorageInterface */
    private $configStorage;

    public function __construct(ConfigStorageInterface $configStorage)
    {
        $this->configStorage = $configStorage;
    }

    /**
     * Generate a configuration file for a CN.
     *
     * @param string $commonName the CN
     *
     * @return string the config file for the CN
     */
    public function get($commonName)
    {
        $configData = [];

        // read the CN configuration
        $cnConfig = $this->configStorage->getConfig($commonName);

        // is the CN disabled?
        if ($cnConfig->getDisable()) {
            return ['disable'];
        }

        return $configData;
    }
}
