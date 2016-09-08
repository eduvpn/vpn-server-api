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
namespace SURFnet\VPN\Server\OpenVpn\Test;

use SURFnet\VPN\Server\OpenVpn\ManagementSocketInterface;
use SURFnet\VPN\Server\OpenVpn\ManagementSocketException;

/**
 * Abstraction to use the OpenVPN management interface using a socket
 * connection.
 */
class TestSocket implements ManagementSocketInterface
{
    /** @var string */
    private $callType;

    /** @var bool */
    private $connectFail;

    /** @var string */
    private $socketAddress;

    public function __construct($callType, $connectFail = false)
    {
        $this->callType = $callType;
        $this->connectFail = $connectFail;
        $this->socketAddress = null;
    }

    public function open($socketAddress, $timeOut = 5)
    {
        $this->socketAddress = $socketAddress;
        if ($this->connectFail) {
            throw new ManagementSocketException('unable to connect to socket');
        }
    }

    public function command($command)
    {
        if ('connections' === $this->callType) {
            if ('tcp://127.42.101.100:11940' === $this->socketAddress) {
                // send back the returnData as an array
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_status_one_client.txt'));
            } else {
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_status_no_clients.txt'));
            }
        } elseif ('kill' === $this->callType) {
            if ('tcp://127.42.101.100:11940' === $this->socketAddress) {
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_kill_success.txt'));
            } else {
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_kill_error.txt'));
            }
        }
    }

    public function close()
    {
        $this->socketAddress = null;
    }
}
