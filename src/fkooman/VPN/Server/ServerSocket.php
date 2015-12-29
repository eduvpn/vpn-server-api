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

/**
 * Abstraction to use the OpenVPN management interface using a socket 
 * connection.
 */
class ServerSocket implements ServerSocketInterface
{
    /** @var resource */
    private $socket;

    /** @var string */
    private $socketAddress;

    /** @var int */
    private $timeOut;

    /**
     * Connect to an OpenVPN management socket.
     *
     * @param string $socketAddress the socket to connect to, e.g.: 
     *                              "tcp://localhost:7505"
     * @param int    $timeOut       the amount of time to wait before 
     *                              giving up on trying to connect
     */
    public function __construct($socketAddress, $timeOut = 5)
    {
        $this->socket = null;
        $this->socketAddress = $socketAddress;
        $this->timeOut = $timeOut;
    }

    public function open()
    {
        $this->socket = @stream_socket_client($this->socketAddress, $errno, $errstr, $this->timeOut);
        if (false === $this->socket) {
            throw new RuntimeException(
                sprintf('%s (%s)', $errstr, $errno)
            );
        }
        // turn off logging as the output may interfere with our management 
        // session, we do not care about the output
        $this->command('log off');
    }

    /**
     * Send an OpenVPN command and get the response.
     *
     * @param string $command the OpenVPN command, e.g. 'status', 'version', 'kill'
     *
     * @return array the response lines in an array, every line as element
     */
    public function command($command)
    {
        $this->write(
            sprintf("%s\n", $command)
        );

        return $this->read();
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
