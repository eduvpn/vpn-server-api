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
namespace fkooman\VPN\Server\OpenVpn;

use fkooman\VPN\Server\OpenVpn\Exception\ServerSocketException;

/**
 * Abstraction to use the OpenVPN management interface using a socket 
 * connection.
 */
class ServerSocket implements ServerSocketInterface
{
    /** @var string */
    private $socketAddress;

    /** @var resource */
    private $socket;

    /**
     * Connect to an OpenVPN management socket.
     *
     * @param string $socketAddress the socket to connect to, e.g.: 
     *                              "tcp://localhost:7505"
     */
    public function __construct($socketAddress)
    {
        $this->socketAddress = $socketAddress;
        $this->socket = null;
    }

    public function open($timeOut = 5)
    {
        $this->socket = @stream_socket_client($this->socketAddress, $errno, $errstr, $timeOut);
        if (false === $this->socket) {
            throw new ServerSocketException(
                sprintf('%s (%s)', $errstr, $errno)
            );
        }
        // turn off logging as the output may interfere with our management 
        // session, we do not care about the output
        $this->command('log off');
    }

    public function command($command)
    {
        $this->requireOpenSocket();
        $this->write(
            sprintf("%s\n", $command)
        );

        return $this->read();
    }

    public function close()
    {
        $this->requireOpenSocket();
        if (false === @fclose($this->socket)) {
            throw new ServerSocketException('unable to close the socket');
        }
    }

    private function write($data)
    {
        if (false === @fwrite($this->socket, $data)) {
            throw new ServerSocketException('unable to write to socket');
        }
    }

    private function read()
    {
        $dataBuffer = array();
        while (!feof($this->socket) && !$this->isEndOfResponse(end($dataBuffer))) {
            if (false === $readData = @fgets($this->socket, 4096)) {
                throw new ServerSocketException('unable to read from socket');
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

    private function requireOpenSocket()
    {
        if (is_null($this->socket)) {
            throw new ServerSocketException('socket not open');
        }
    }
}
