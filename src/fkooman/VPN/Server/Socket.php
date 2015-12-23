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

use RuntimeException;

class Socket
{
    /** @var resource */
    private $socket;

    public function __construct($socketAddress, $timeOut = 5)
    {
        $this->socket = @stream_socket_client($socketAddress, $errno, $errstr, $timeOut);
        if (!$this->socket) {
            throw new RuntimeException(sprintf('%s (%s)', $errstr, $errno));
        }
    }

    /**
     * Send an OpenVPN command and get the response.
     *
     * @param string $cmd the OpenVPN command, e.g. 'status', 'version', 'kill'
     *
     * @return array the response lines in an array, every line as element
     */
    public function command($cmd)
    {
        $this->write(sprintf("%s\n", $cmd));
        $dataArray = $this->read();

        return $dataArray;
    }

    /**
     * Close the socket connection.
     */
    public function close()
    {
        if (false === @fclose($this->socket)) {
            throw new RuntimeException('unable to close the socket');
        }
    }

    private function write($data)
    {
        if (false === @fwrite($this->socket, $data)) {
            throw new RuntimeException('unable to write to socket');
        }
    }

    private function read()
    {
        $dataBuffer = array();
        while (!feof($this->socket) && !$this->isEndOfResponse(end($dataBuffer))) {
            if (false === $readData = @fgets($this->socket, 4096)) {
                throw new RuntimeException('unable to read from socket');
            }
            $dataBuffer[] = trim($readData);
        }

        return $dataBuffer;
    }

    private function isEndOfResponse($lastLine)
    {
        $endMarkers = array('END', 'SUCCESS: ', 'ERROR: ');
        foreach ($endMarkers as $endMarker) {
            if (0 === strpos($lastLine, $endMarker)) {
                return true;
            }
        }

        return false;
    }
}
