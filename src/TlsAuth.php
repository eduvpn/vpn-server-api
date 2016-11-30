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

namespace SURFnet\VPN\Server;

use RuntimeException;
use SURFnet\VPN\Common\FileIO;

class TlsAuth
{
    /** @var string */
    private $dataDir;

    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function init()
    {
        $taFile = sprintf('%s/ta.key', $this->dataDir);

        // generate the TA file if it does not exist
        if (!@file_exists($taFile)) {
            $this->execOpenVpn(['--genkey', '--secret', $taFile]);
        }
    }

    public function get()
    {
        $taFile = sprintf('%s/ta.key', $this->dataDir);

        return FileIO::readFile($taFile);
    }

    private function execOpenVpn(array $argv)
    {
        $command = sprintf(
            '/usr/sbin/openvpn %s >/dev/null 2>/dev/null',
            implode(' ', $argv)
        );

        exec(
            $command,
            $commandOutput,
            $returnValue
        );

        if (0 !== $returnValue) {
            throw new RuntimeException(
                sprintf('command "%s" did not complete successfully: "%s"', $command, $commandOutput)
            );
        }
    }
}
