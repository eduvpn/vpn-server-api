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

    public function getStaticAddresses($commonName)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseCcd($commonName);

        #--ifconfig-push local remote-netmask [alias] 
        #--ifconfig-ipv6-push ipv6addr/bits ipv6remote 
        $v4 = null;
        $v6 = null;

        foreach ($clientConfig as $k => $v) {
            if (0 === strpos($v, 'ifconfig-push')) {
                $v4 = trim(substr($v, 14));
            }
            if (0 === strpos($v, 'ifconfig-ipv6-push')) {
                $v6 = trim(substr($v, 19));
            }
        }

        return array(
            'v4' => $v4,
            'v6' => $v6,
        );
    }

    public function setStaticAddresses($commonName, $v4, $v6)
    {
        Utils::validateCommonName($commonName);

        $clientConfig = $this->parseCcd($commonName);
        $v4Pos = null;
        $v6Pos = null;

        foreach ($clientConfig as $k => $v) {
            if (0 === strpos($v, 'ifconfig-push')) {
                $v4Pos = $k;
            }
            if (0 === strpos($v, 'ifconfig-ipv6-push')) {
                $v6Pos = $k;
            }
        }

        // XXX clean up the stuff below...
        if (!is_null($v4Pos)) {
            if (!is_null($v4)) {
                $clientConfig[$v4Pos] = sprintf('ifconfig-push %s', $v4);
            } else {
                unset($clientConfig[$v4Pos]);
            }
        } else {
            if (!is_null($v4)) {
                $clientConfig[] = sprintf('ifconfig-push %s', $v4);
            }
        }

        if (!is_null($v6Pos)) {
            if (!is_null($v6)) {
                $clientConfig[$v6Pos] = sprintf('ifconfig-ipv6-push %s', $v6);
            } else {
                unset($clientConfig[$v6Pos]);
            }
        } else {
            if (!is_null($v6)) {
                $clientConfig[] = sprintf('ifconfig-ipv6-push %s', $v6);
            }
        }

        $this->writeFile($commonName, $clientConfig);
    }

    private function writeFile($commonName, array $clientConfig)
    {
        $commonNamePath = sprintf('%s/%s', $this->ccdPath, $commonName);

        if (false === @file_put_contents($commonNamePath, implode(PHP_EOL, $clientConfig).PHP_EOL)) {
            throw new RuntimeException('unable to write to CCD file');
        }
    }
}
