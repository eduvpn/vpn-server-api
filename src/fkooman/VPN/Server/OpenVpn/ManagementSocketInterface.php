<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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
