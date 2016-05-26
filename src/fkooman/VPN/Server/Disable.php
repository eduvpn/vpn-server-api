<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

use fkooman\IO\IOInterface;
use fkooman\IO\IO;

class Disable
{
    /** @var string */
    private $configDir;

    /** @var \fkooman\IO\IOInterface */
    private $io;

    public function __construct($configDir, IOInterface $io = null)
    {
        $this->configDir = $configDir;
        if (is_null($io)) {
            $io = new IO();
        }
        $this->io = $io;
    }

    public function getDisabled()
    {
        $disabledCommonNames = [];
        foreach ($this->io->readFolder($this->configDir, '*') as $f) {
            $disabledCommonNames[] = basename($f);
        }

        return $disabledCommonNames;
    }

    public function getDisable($commonName)
    {
        $commonNameFile = sprintf('%s/%s', $this->configDir, $commonName);

        return $this->io->isFile($commonNameFile);
    }

    public function setDisable($commonName, $isDisabled)
    {
        if ($isDisabled === $this->getDisable($commonName)) {
            // already in correct state, do nothing
            return false;
        }
        $commonNameFile = sprintf('%s/%s', $this->configDir, $commonName);
        if ($isDisabled) {
            // disable
            $this->io->writeFile($commonNameFile, strval($this->io->getTime()), true, 0751);
        } else {
            // remove file, so it is no longer disabled
            $this->io->deleteFile($commonNameFile);
        }

        return true;
    }
}
