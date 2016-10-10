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

use SURFnet\VPN\Common\FileIO;
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
        FileIO::createDir($this->disableDir, 0711);
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

    public function isDisabled($commonName)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $commonName);

        return @file_exists($disableFile);
    }

    public function setDisabled($commonName)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $commonName);
        FileIO::writeFile($disableFile, time(), 0644);
    }

    public function setEnabled($commonName)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $commonName);
        FileIO::deleteFile($disableFile);
    }
}
