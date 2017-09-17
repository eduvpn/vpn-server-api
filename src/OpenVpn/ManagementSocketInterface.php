<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
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
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException if the socket cannot be opened
     *                                                                     within timeout
     */
    public function open($socketAddress, $timeOut = 5);

    /**
     * Send an OpenVPN command and get the response.
     *
     * @param string $command a OpenVPN management command and parameters
     *
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException in case read/write fails or
     *                                                                     socket is not open
     *
     * @return array the response lines as array values
     */
    public function command($command);

    /**
     * Close the socket connection.
     *
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException if socket is not open
     */
    public function close();
}
