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

    /** @var IP */
    private $ipRange;

    /** @var IP */
    private $poolRange;

    public function __construct($staticConfigDir, IP $ipRange, IP $poolRange)
    {
        $this->staticConfigDir = $staticConfigDir;
        $this->ipRange = $ipRange;
        $this->poolRange = $poolRange;
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

    public function getStaticAddresses($userId = null)
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
            $ip = $this->getStaticAddress($commonName);
            if (!is_null($ip['v4'])) {
                $staticAddresses[$commonName] = $ip;
            }
        }

        return $staticAddresses;
    }

    public function getStaticAddress($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseConfig($commonName);

        $v4 = null;
        if (array_key_exists('v4', $clientConfig)) {
            $v4 = $clientConfig['v4'];
        }

        return array(
            'v4' => $v4,
        );
    }

    public function setStaticAddresses($commonName, $v4)
    {
        Utils::validateCommonName($commonName);
        if (!is_null($v4)) {
            Utils::validateAddress($v4);

            // IP MUST be in ipRange
            if (!$this->ipRange->inRange($v4)) {
                throw new RuntimeException('IP is out of range');
            }

            // IP MUST NOT be in poolRange (including network and broadcast
            if ($this->poolRange->inRange($v4, true)) {
                throw new RuntimeException('IP cannot be in poolRange');
            }

            // cannot be server IP (we assume for now firstHost is server IP
            if ($v4 === $this->ipRange->getFirstHost()) {
                throw new RuntimeException('IP cannot be server IP');
            }

            // XXX make sure it is not already in use by another config, it is
            // okay if it is this config!
            $staticAddresses = $this->getStaticAddresses();
            foreach ($staticAddresses as $cn => $c) {
                if ($c['v4'] === $v4) {
                    if ($commonName === $cn) {
                        // the commonName is allowed to have the same address,
                        // i.e. when setting the same IPv4 address that was 
                        // already assigned to that CN
                        continue;
                    }

                    throw new RuntimeException(sprintf('IP address already in use by "%s"', $cn));
                }
            }
        }   
        $clientConfig = $this->parseConfig($commonName);
        $clientConfig['v4'] = $v4;
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
