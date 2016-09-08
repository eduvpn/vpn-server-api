<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Server\Api;

use RuntimeException;

/**
 * Manages common name configuration.
 *
 * XXX deal better with exceptions, not everything is a RuntimeException,
 */
class CommonNames
{
    /** @var string */
    private $disableDir;

    public function __construct($dataDir)
    {
        $this->disableDir = sprintf('%s/disabled', $dataDir);
        self::createDir($this->disableDir);
    }

    public function getDisabled()
    {
        $disabledList = [];
        if (false === $fileList = glob(sprintf('%s/*', $this->disableDir), GLOB_ERR)) {
            throw new RuntimeException(sprintf('unable to read directory "%s"', $this->disableDir));
        }

        foreach ($fileList as $fileName) {
            $disabledList[] = basename($fileName);
        }

        return $disabledList;
    }

    public function setDisabled($commonName)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $commonName);
        if (false === @file_put_contents($disableFile, time())) {
            throw new RuntimeException(sprintf('unable to write file "%s"', $disableFile));
        }
    }

    public function setEnabled($commonName)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $commonName);
        if (false === @unlink($disableFile)) {
            throw new RuntimeException(sprintf('unable to delete file "%s"', $disableFile));
        }
    }

    private static function createDir($dirName)
    {
        if (!@file_exists($dirName)) {
            if (false === @mkdir($dirName, 0700, true)) {
                throw new RuntimeException(sprintf('unable to create directory "%s"', $dirName));
            }
        }
    }
}
