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

use RuntimeException;
use fkooman\Json\Json;

class FileConfigStorage implements ConfigStorageInterface
{
    /** @var string */
    private $usersConfigDir;

    /** @var string */
    private $commonNamesConfigDir;

    public function __construct($configDir)
    {
        $usersConfigDir = sprintf('%s/users', $configDir);
        self::checkMakeDirectory($usersConfigDir);
        $commonNamesConfigDir = sprintf('%s/common_names', $configDir);
        self::checkMakeDirectory($commonNamesConfigDir);

        $this->usersConfigDir = $usersConfigDir;
        $this->commonNamesConfigDir = $commonNamesConfigDir;
    }

    /**
     * Get configuration specific to a particular user.
     *
     * @return UserConfig
     */
    public function getUserConfig($userId)
    {
        $userData = $this->readFile(
            sprintf('%s/%s', $usersConfigDir, $userId)
        );

        return new UserConfig($userData);
    }

    /**
     * Set the configuration for a particular user.
     */
    public function setUserConfig($userId, UserConfig $userConfig)
    {
        $this->writeFile(
            sprintf('%s/%s', $this->usersConfigDir, $userId),
            $userConfig->toArray()
        );
    }

    /**
     * Get the configuration for a particular common name.
     *
     * @return CommonNameConfig
     */
    public function getCommonNameConfig($commonName)
    {
        $commonNameData = $this->readFile(
            sprintf('%s/%s', $this->commonNamesConfigDir, $commonName)
        );

        return new CommonNameConfig($commonNameData);
    }

    public function getAllCommonNameConfig($userId)
    {
        $configArray = [];
        $pathFilter = sprintf('%s/*', $this->commonNamesConfigDir);
        if (!is_null($userId)) {
            $pathFilter = sprintf('%s/%s_*', $this->commonNamesConfigDir, $userId);
        }
        foreach (glob($pathFilter) as $commonNamePath) {
            $commonName = basename($commonNamePath);
            $configArray[$commonName] = $this->getCommonNameConfig($commonName)->toArray();
        }

        return $configArray;
    }

    /** 
     * Set the configuration for a particular common name.
     */
    public function setCommonNameConfig($commonName, CommonNameConfig $commonNameConfig)
    {
        $this->writeFile(
            sprintf('%s/%s', $this->commonNamesConfigDir, $commonName),
            $commonNameConfig->toArray()
        );
    }

    private function readFile($fileName)
    {
        if (false === $fileContent = @file_get_contents($fileName)) {
            return [];
        }

        return Json::decode($fileContent);
    }

    private function writeFile($fileName, array $fileContent)
    {
        if (false === @file_put_contents($fileName, Json::encode($fileContent))) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $fileName));
        }
    }

    private static function checkMakeDirectory($dirName)
    {
        if (!is_dir($dirName)) {
            if (false === @mkdir($dirName, 0755, true)) {
                throw new RuntimeException('unable to create directory "%s"', $dirName);
            }
        }
    }
}
