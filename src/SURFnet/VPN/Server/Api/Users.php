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
 * Manages user configuration.
 *
 * XXX deal better with exceptions, not everything is a RuntimeException,
 */
class Users
{
    /** @var string */
    private $disableDir;

    /** @var string */
    private $otpDir;

    /** @var string */
    private $vootDir;

    public function __construct($dataDir)
    {
        $this->disableDir = sprintf('%s/disabled', $dataDir);
        FileIO::createDir($this->disableDir, 0711);
        $this->otpDir = sprintf('%s/otp_secrets', $dataDir);
        FileIO::createDir($this->otpDir, 0711);
        $this->vootDir = sprintf('%s/voot_tokens', $dataDir);
        FileIO::createDir($this->vootDir, 0711);
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

    public function isDisabled($userId)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $userId);

        return @file_exists($disableFile);
    }

    public function setDisabled($userId)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $userId);
        FileIO::writeFile($disableFile, time(), 0644);
    }

    public function setEnabled($userId)
    {
        $disableFile = sprintf('%s/%s', $this->disableDir, $userId);
        FileIO::deleteFile($disableFile);
    }

    public function setOtpSecret($userId, $otpSecret)
    {
        // do not allow override of the OTP secret
        if (false !== $this->hasOtpSecret($userId)) {
            // XXX should not be RuntimeException, but e.g. UsersException
            throw new RuntimeException('cannot overwrite OTP secret');
        }
        $otpFile = sprintf('%s/%s', $this->otpDir, $userId);
        FileIO::writeFile($otpFile, $otpSecret, 0644);
    }

    public function deleteOtpSecret($userId)
    {
        $otpFile = sprintf('%s/%s', $this->otpDir, $userId);
        FileIO::deleteFile($otpFile);
    }

    public function hasOtpSecret($userId)
    {
        $otpFile = sprintf('%s/%s', $this->otpDir, $userId);

        return @file_exists($otpFile);
    }

    public function setVootToken($userId, $vootToken)
    {
        $vootFile = sprintf('%s/%s', $this->vootDir, $userId);
        FileIO::writeFile($vootFile, $vootToken, 0644);
    }

    public function hasVootToken($userId)
    {
        $vootFile = sprintf('%s/%s', $this->vootDir, $userId);

        return @file_exists($vootFile);
    }
}
