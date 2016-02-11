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

use RuntimeException;

/**
 * Manage the Client Configuration Directory (CCD) used by the OpenVPN 
 * instances running on this machine. It can be used to set client specific
 * configurations based on the CN.
 */
class CcdHandler
{
    /** @var string */
    private $ccdPath;

    public function __construct($ccdPath)
    {
        $this->ccdPath = $ccdPath;
    }

    private function parseCcd($commonName)
    {
        $commonNamePath = sprintf('%s/%s', $this->ccdPath, $commonName);
        $clientConfig = array();

        $handle = @fopen($commonNamePath, 'r');
        if ($handle) {
            while (false !== $line = fgets($handle, 128)) {
                $clientConfig[] = trim($line);
            }
            fclose($handle);
        }

        return $clientConfig;
    }

    public function disableCommonName($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseCcd($commonName);
        foreach ($clientConfig as $k => $v) {
            if ('disable' === $v) {
                // already disabled
                return false;
            }
        }

        // not yet disabled
        $clientConfig[] = 'disable';

        $this->writeFile($commonName, $clientConfig);

        return true;
    }

    public function enableCommonName($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseCcd($commonName);
        foreach ($clientConfig as $k => $v) {
            if ('disable' === $v) {
                // it is disabled
                unset($clientConfig[$k]);
                $this->writeFile($commonName, $clientConfig);

                return true;
            }
        }

        // not disabled
        return false;
    }

    public function isDisabled($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseCcd($commonName);
        foreach ($clientConfig as $k => $v) {
            if ('disable' === $v) {
                // disabled
                return true;
            }
        }

        // not disabled
        return false;
    }

    public function getDisabledCommonNames($userId = null)
    {
        if (!is_null($userId)) {
            Utils::validateUserId($userId);
        }

        $disabledCommonNames = array();
        $pathFilter = sprintf('%s/*', $this->ccdPath);
        if (!is_null($userId)) {
            $pathFilter = sprintf('%s/%s_*', $this->ccdPath, $userId);
        }

        foreach (glob($pathFilter) as $commonNamePath) {
            $commonName = basename($commonNamePath);

            if ($this->isDisabled($commonName)) {
                $disabledCommonNames[] = $commonName;
            }
        }

        return $disabledCommonNames;
    }

    public function getStaticIpAddress($commonName)
    {
    }

    public function setStaticIpAddress($commonName, $v4, $v6)
    {
        if (!is_null($v4)) {
        }

        if (!is_null($v6)) {
        }
    }

    private function writeFile($commonName, array $clientConfig)
    {
        $commonNamePath = sprintf('%s/%s', $this->ccdPath, $commonName);

        if (false === @file_put_contents($commonNamePath, implode(PHP_EOL, $clientConfig).PHP_EOL)) {
            throw new RuntimeException('unable to write to CCD file');
        }
    }
}
