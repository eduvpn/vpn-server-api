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
namespace SURFnet\VPN\Server\OpenVpn;

use SURFnet\VPN\Server\OpenVpn\Exception\ManagementSocketException;

/**
 * Abstraction to use the OpenVPN management interface using a socket
 * connection.
 */
class ManagementSocket implements ManagementSocketInterface
{
    /** @var resource */
    private $socket;

    public function __construct()
    {
        $this->socket = null;
    }

    /**
     * Connect to an OpenVPN management socket.
     *
     * @param string $socketAddress the socket to connect to, e.g.:
     *                              "tcp://localhost:7505"
     */
    public function open($socketAddress, $timeOut = 5)
    {
        $this->socket = @stream_socket_client($socketAddress, $errno, $errstr, $timeOut);
        if (false === $this->socket) {
            throw new ManagementSocketException(
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
            throw new ManagementSocketException('unable to close the socket');
        }
    }

    private function write($data)
    {
        if (false === @fwrite($this->socket, $data)) {
            throw new ManagementSocketException('unable to write to socket');
        }
    }

    private function read()
    {
        $dataBuffer = array();
        while (!feof($this->socket) && !$this->isEndOfResponse(end($dataBuffer))) {
            if (false === $readData = @fgets($this->socket, 4096)) {
                throw new ManagementSocketException('unable to read from socket');
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
            throw new ManagementSocketException('socket not open');
        }
    }
}
