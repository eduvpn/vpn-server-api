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

use Socket\Raw\Factory;
use Socket\Raw\Socket;
use Exception;

class SocketStatus
{
    /** @var \Socket\Raw\Socket */
    private $socket;

    public function __construct($socketAddress)
    {
        $factory = new Factory();
        $this->socket = $factory->createClient($socketAddress);
        // read banner
        $this->readAll();

        // disable log
        $this->socket->write("log off\n");

        // read disable log output
        $this->readAll();
    }

    public function fetchStatus()
    {
        // ask for status
        $this->socket->write("status\n");

        // read and return status
        return $this->readStatus();
    }

    public function fetchVersion()
    {
        // ask for version
        $this->socket->write("version\n");

        // read and return status
        return $this->readVersion();
    }

    private function readLine()
    {
        return $this->socket->read(256, PHP_NORMAL_READ);
    }

    private function readStatus()
    {
        $msg = '';
        do {
            $inputLine = $this->readLine();
            $msg .= $inputLine;
        } while (0 !== strpos($inputLine, 'END'));

        return $msg;
    }

    private function readVersion()
    {
        $versionPrefix = 'OpenVPN Version: OpenVPN ';
        do {
            $inputLine = $this->readLine();
            if (0 === strpos($inputLine, $versionPrefix)) {
                $versionStart = substr($inputLine, strlen($versionPrefix));

                return substr($versionStart, 0, strpos($versionStart, ' '));
            }
        } while (0 !== strpos($inputLine, 'END'));

        return 'Unknown';
    }

    private function readAll()
    {
        $availableData = $this->socket->read(8192);
        while ($this->socket->selectRead()) {
            $availableData .= $this->socket->read(8192);
        }

        return $availableData;
    }

    public function killClient($commonName)
    {
        #kill aa3f6fade450f12aa891bf066b86921344e2a1f1_phone
        #SUCCESS: common name 'aa3f6fade450f12aa891bf066b86921344e2a1f1_phone' found, 1 client(s) killed

        $this->socket->write(sprintf("kill %s\n", $commonName));
        if (0 !== strpos($this->readLine(), 'SUCCESS')) {
            throw new Exception('unable to kill client');
        }
    }
}
