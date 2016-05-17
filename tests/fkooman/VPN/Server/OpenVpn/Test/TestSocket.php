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

namespace fkooman\VPN\Server\OpenVpn\Test;

use fkooman\VPN\Server\OpenVpn\ManagementSocketInterface;
use fkooman\IO\IO;

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
            throw new ServerSocketException('unable to connect to socket');
        }
    }

    public function command($command)
    {
        $io = new IO();

        if ('connections' === $this->callType) {
            if ('tcp://127.42.0.1:11942' === $this->socketAddress) {
                // send back the returnData as an array
                return explode("\n", $io->readFile(dirname(__DIR__).'/data/socket/openvpn_23_status_one_client.txt'));
            } else {
                return explode("\n", $io->readFile(dirname(__DIR__).'/data/socket/openvpn_23_status_no_clients.txt'));
            }
        } elseif ('kill' === $this->callType) {
            if ('tcp://127.42.0.1:11941' === $this->socketAddress) {
                return explode("\n", $io->readFile(dirname(__DIR__).'/data/socket/openvpn_23_kill_success.txt'));
            } else {
                return explode("\n", $io->readFile(dirname(__DIR__).'/data/socket/openvpn_23_kill_error.txt'));
            }
        }
    }

    public function close()
    {
        $this->socketAddress = null;
    }
}
