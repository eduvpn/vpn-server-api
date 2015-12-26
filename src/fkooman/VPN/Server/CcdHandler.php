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

class CcdHandler
{
    /** @var string */
    private $ccdPath;

    const DISABLE_REGEXP = "/^disable\n$/mS";

    public function __construct($ccdPath)
    {
        $this->ccdPath = $ccdPath;
    }

    public function disableCommonName($commonName)
    {
        Utils::validateCommonName($commonName);

        // we add 'disable' to the CCD for this particular common name
        $commonNamePath = sprintf('%s/%s', $this->ccdPath, $commonName);
        if (false === $fileContent = self::readFile($commonNamePath)) {
            // unable to read file, write 'disable' to it
            self::writeFile($commonNamePath, "disable\n");

            return true;
        }

        // check if we find 'disable' in the file
        if (1 !== preg_match(self::DISABLE_REGEXP, $fileContent)) {
            // not found
            self::writeFile($commonNamePath, "disable\n");

            return true;
        }

        // found, already disabled, do nothing
        return false;
    }

    public function enableCommonName($commonName)
    {
        Utils::validateCommonName($commonName);

        // we remove 'disable' from the CCD for this particular common name
        $commonNamePath = sprintf('%s/%s', $this->ccdPath, $commonName);
        if (false === $fileContent = self::readFile($commonNamePath)) {
            // unable to read file, so CN is not disabled
            return false;
        }
        // check if we find 'disable' in the file
        if (1 === preg_match(self::DISABLE_REGEXP, $fileContent)) {
            // found
            $fileContent = preg_replace(self::DISABLE_REGEXP, '', $fileContent);
            self::writeFile($commonNamePath, $fileContent, false);

            return true;
        }

        // not found, not disabled, do nothing
        return false;
    }

    public function getDisabledCommonNames()
    {
        $disabledCommonNames = array();
        foreach (glob($this->ccdPath.'/*') as $commonNamePath) {
            if (false !== $fileContent = self::readFile($commonNamePath)) {
                // read the file, check if it is disabled
                if (1 === preg_match(self::DISABLE_REGEXP, $fileContent)) {
                    // disabled
                    $disabledCommonNames[] = basename($commonNamePath);
                }
            }
        }

        return $disabledCommonNames;
    }

    private static function readFile($filePath)
    {
        return @file_get_contents($filePath);
    }

    private static function writeFile($filePath, $fileContent, $appendFile = true)
    {
        $opt = $appendFile ? FILE_APPEND : 0;
        if (false === @file_put_contents($filePath, $fileContent, $opt)) {
            throw new RuntimeException('unable to write to CCD file');
        }
    }
}
