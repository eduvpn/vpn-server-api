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

class OtpSecret
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
     * Get a list of users that have an OTP secret set.
     */
    public function getOtpSecrets()
    {
        $otpSecrets = [];
        foreach ($this->io->readFolder($this->configDir, '*') as $f) {
            $otpSecrets[] = basename($f);
        }

        return $otpSecrets;
    }

    /**
     * Check whether a particular user has an OTP secret set.
     */
    public function getOtpSecret($userId)
    {
        $otpSecretFile = sprintf('%s/%s', $this->configDir, $userId);

        if ($this->io->isFile($otpSecretFile)) {
            return $this->io->readFile($otpSecretFile);
        }

        return false;
    }

    /**
     * Set or delete the OTP secret for a particular user.
     */
    public function setOtpSecret($userId, $otpSecret)
    {
        $otpSecretFile = sprintf('%s/%s', $this->configDir, $userId);

        if (false === $otpSecret) {
            // remove the secret
            if ($this->io->isFile($otpSecretFile)) {
                $this->io->deleteFile($otpSecretFile);

                return true;
            }

            return false;
        }

        // set the secret, if it is not yet set, do not allow override
        if ($this->io->isFile($otpSecretFile)) {
            return false;
        }

        $this->io->writeFile($otpSecretFile, $otpSecret, true, 0751);

        return true;
    }
}
