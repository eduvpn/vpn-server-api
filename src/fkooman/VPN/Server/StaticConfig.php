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
namespace fkooman\VPN\Server;

use fkooman\Json\Json;
use RuntimeException;

/**
 * Manage static configuration for configurations.
 * Features:
 * - disable a configuration based on CN
 * - set static IPv4/IPv6 address for a CN.
 */
class StaticConfig
{
    /** @var string */
    private $staticConfigDir;

    public function __construct($staticConfigDir)
    {
        $this->staticConfigDir = $staticConfigDir;
    }

    private function parseConfig($commonName)
    {
        $commonNamePath = sprintf('%s/%s', $this->staticConfigDir, $commonName);
        // XXX do something if file does not exist
        try {
            return Json::decodeFile($commonNamePath);
        } catch (RuntimeException $e) {
            return [];
        }
    }

    public function disableCommonName($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseConfig($commonName);
        if (array_key_exists('disable', $clientConfig) && $clientConfig['disable']) {
            // already disabled
            return false;
        }

        $clientConfig['disable'] = true;
        $this->writeFile($commonName, $clientConfig);

        return true;
    }

    public function enableCommonName($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseConfig($commonName);
        if (array_key_exists('disable', $clientConfig) && $clientConfig['disable']) {
            // it is disabled, enable it
            $clientConfig['disable'] = false;
            $this->writeFile($commonName, $clientConfig);

            return true;
        }

        // it is not disabled
        return false;
    }

    public function isDisabled($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseConfig($commonName);

        return array_key_exists('disable', $clientConfig) && $clientConfig['disable'];
    }

    public function getDisabledCommonNames($userId = null)
    {
        if (!is_null($userId)) {
            Utils::validateUserId($userId);
        }

        $disabledCommonNames = array();
        $pathFilter = sprintf('%s/*', $this->staticConfigDir);
        if (!is_null($userId)) {
            $pathFilter = sprintf('%s/%s_*', $this->staticConfigDir, $userId);
        }

        foreach (glob($pathFilter) as $commonNamePath) {
            $commonName = basename($commonNamePath);

            if ($this->isDisabled($commonName)) {
                $disabledCommonNames[] = $commonName;
            }
        }

        return $disabledCommonNames;
    }

    public function getAllStaticAddresses($userId = null)
    {
        if (!is_null($userId)) {
            Utils::validateUserId($userId);
        }

        $staticAddresses = array();
        $pathFilter = sprintf('%s/*', $this->staticConfigDir);
        if (!is_null($userId)) {
            $pathFilter = sprintf('%s/%s_*', $this->staticConfigDir, $userId);
        }

        foreach (glob($pathFilter) as $commonNamePath) {
            $commonName = basename($commonNamePath);
            $staticAddresses[$commonName] = $this->getStaticAddresses($commonName);
        }

        return $staticAddresses;
    }

    public function getStaticAddresses($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseConfig($commonName);

        $v4 = null;
        if (array_key_exists('v4', $clientConfig)) {
            $v4 = $clientConfig['v4'];
        }
        $v6 = null;
        if (array_key_exists('v6', $clientConfig)) {
            $v6 = $clientConfig['v6'];
        }

        return array(
            'v4' => $v4,
            'v6' => $v6,
        );
    }

    public function setStaticAddresses($commonName, $v4, $v6)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseConfig($commonName);

        $clientConfig['v4'] = $v4;
        $clientConfig['v6'] = $v6;
        $this->writeFile($commonName, $clientConfig);

        return true;
    }

    private function writeFile($commonName, array $clientConfig)
    {
        $commonNamePath = sprintf('%s/%s', $this->staticConfigDir, $commonName);

        if (false === @file_put_contents($commonNamePath, Json::encode($clientConfig))) {
            throw new RuntimeException('unable to write to static configuration file');
        }
    }
}
