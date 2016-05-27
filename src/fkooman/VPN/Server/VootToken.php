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

class VootToken
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

    /**
     * Check whether a particular user has a VOOT token set.
     */
    public function getVootToken($userId)
    {
        $vootTokenFile = sprintf('%s/%s', $this->configDir, $userId);

        if ($this->io->isFile($vootTokenFile)) {
            return $this->io->readFile($vootTokenFile);
        }

        return false;
    }

    /**
     * Set or delete the VOOT token for a particular user.
     */
    public function setVootToken($userId, $vootToken)
    {
        $vootTokenFile = sprintf('%s/%s', $this->configDir, $userId);

        if (false === $vootToken) {
            // remove the token
            if ($this->io->isFile($vootTokenFile)) {
                $this->io->deleteFile($vootTokenFile);

                return true;
            }

            return false;
        }

        // set the token, if it is already set override
        $this->io->writeFile($vootTokenFile, $vootToken, true);

        return true;
    }
}
