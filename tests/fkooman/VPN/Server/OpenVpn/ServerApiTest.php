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

require_once __DIR__.'/Test/TestSocket.php';

use PHPUnit_Framework_TestCase;
use fkooman\VPN\Server\OpenVpn\Test\TestSocket;

class ServerApiTest extends PHPUnit_Framework_TestCase
{
    public function testUnableToConnect()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_status_one_client.txt'), true);
        $api = new ServerApi($socket);
        $this->assertFalse(
            $api->status()
        );
    }

    public function testStatus()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_status_one_client.txt'));
        $api = new ServerApi($socket);
        $this->assertSame(
            array(
                array(
                    'common_name' => 'fkooman_samsung_i9300',
                    'real_address' => '91.64.87.183:43103',
                    'bytes_in' => 18301,
                    'bytes_out' => 30009,
                    'connected_since' => 1451323167,
                    'virtual_address' => array(
                        'fd00:4242:4242::1003',
                        '10.42.42.5',
                    ),
                ),
            ),
            $api->status()
        );
    }

    public function testKillSuccess()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_kill_success.txt'));
        $api = new ServerApi($socket);
        $this->assertTrue(
            $api->kill('dummy')
        );
    }

    public function testKillError()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_kill_error.txt'));
        $api = new ServerApi($socket);
        $this->assertFalse(
            $api->kill('dummy')
        );
    }

    private static function readFile($fileName)
    {
        return @file_get_contents(
            sprintf(__DIR__.'/data/socket/%s', $fileName)
        );
    }
}
