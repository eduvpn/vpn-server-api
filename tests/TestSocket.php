<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests;

use SURFnet\VPN\Server\OpenVpn\Exception\ManagementSocketException;
use SURFnet\VPN\Server\OpenVpn\ManagementSocketInterface;

/**
 * Abstraction to use the OpenVPN management interface using a socket
 * connection.
 */
class TestSocket implements ManagementSocketInterface
{
    /** @var bool */
    private $connectFail;

    /** @var string|null */
    private $socketAddress;

    public function __construct($connectFail = false)
    {
        $this->connectFail = $connectFail;
        $this->socketAddress = null;
    }

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
    public function open($socketAddress, $timeOut = 5)
    {
        $this->socketAddress = $socketAddress;
        if ($this->connectFail) {
            throw new ManagementSocketException('unable to connect to socket');
        }
    }

    /**
     * Send an OpenVPN command and get the response.
     *
     * @param string $command a OpenVPN management command and parameters
     *
     * @return array the response lines as array values
     *
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException in case read/write fails or
     *                                                                     socket is not open
     */
    public function command($command)
    {
        if ('status 2' === $command) {
            if ('tcp://127.0.0.1:11940' === $this->socketAddress) {
                // send back the returnData as an array
                return explode("\n", file_get_contents(__DIR__.'/data/socket/status_with_clients.txt'));
            } else {
                return explode("\n", file_get_contents(__DIR__.'/data/socket/status_no_clients.txt'));
            }
        } elseif ('kill' === $command) {
            if ('tcp://127.0.0.1:11940' === $this->socketAddress) {
                return explode("\n", file_get_contents(__DIR__.'/data/socket/kill_success.txt'));
            } else {
                return explode("\n", file_get_contents(__DIR__.'/data/socket/kill_error.txt'));
            }
        }
    }

    /**
     * Close the socket connection.
     *
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException if socket is not open
     */
    public function close()
    {
        $this->socketAddress = null;
    }
}
