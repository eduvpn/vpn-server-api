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

namespace fkooman\VPN\Server\UserConfig\Test;

use fkooman\IO\IOInterface;
use fkooman\Json\Json;
use RuntimeException;

class TestIO implements IOInterface
{
    /** @var array */
    private $fs;

    public function __construct()
    {
        $this->fs = [
            '/tmp/foo' => Json::encode(
                [
                    'otp_secret' => '1234567891234567',
                    'disable' => true,
                ]
            ),
        ];
    }

    public function getTime()
    {
        // NOP
    }

    public function getRandom($byteLength = 16, $rawBytes = false)
    {
        // NOP
    }

    public function isFile($filePath)
    {
        return array_key_exists($filePath, $this->fs);
    }

    public function readFile($filePath)
    {
        if ($this->isFile($filePath)) {
            return $this->fs[$filePath];
        }

        throw new RuntimeException(sprintf('no such file "%s"', $filePath));
    }

    public function readFolder($folderPath, $fileFilter = '*')
    {
        return array_keys($this->fs);
    }

    public function writeFile($filePath, $fileContent)
    {
        $this->fs[$filePath] = $fileContent;
    }
}
