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

interface ManagementSocketInterface
{
    /**
     * Open the socket.
     *
     * @param string $socketAddress the socket to connect to, e.g.:
     *                              "tcp://localhost:7505"
     * @param int    $timeOut       the amount of time to wait before
     *                              giving up on trying to connect
     *
     * @throws Exception\ServerSocketException if the socket cannot be opened
     *                                         within timeout
     */
    public function open($socketAddress, $timeOut = 5);

    /**
     * Send an OpenVPN command and get the response.
     *
     * @param string $command a OpenVPN management command and parameters
     *
     * @return array the response lines as array values
     *
     * @throws Exception\ServerSocketException in case read/write fails or
     *                                         socket is not open
     */
    public function command($command);

    /**
     * Close the socket connection.
     *
     * @throws Exception\ServerSocketException if socket is not open
     */
    public function close();
}
