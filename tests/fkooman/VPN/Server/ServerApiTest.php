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

require_once __DIR__.'/Test/TestSocket.php';

use PHPUnit_Framework_TestCase;
use fkooman\VPN\Server\Test\TestSocket;

class ServerApiTest extends PHPUnit_Framework_TestCase
{
    public function testVersion()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_version.txt'));
        $api = new ServerApi($socket);
        $this->assertSame(
            'OpenVPN 2.3.8 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Aug  4 2015',
            $api->version()
        );
    }

    public function testLoadStats()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_load_stats.txt'));
        $api = new ServerApi($socket);
        $this->assertSame(
            array(
                'number_of_clients' => 0,
                'bytes_in' => 2224463,
                'bytes_out' => 6102370,
            ),
            $api->loadStats()
        );
    }

    public function testStatusNoClients()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_status_no_clients.txt'));
        $api = new ServerApi($socket);
        $this->assertSame(
            array(
            ),
            $api->status()
        );
    }

    public function testStatusOneClient()
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

    public function testStatusTwoClients()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_status_two_clients.txt'));
        $api = new ServerApi($socket);
        $this->assertSame(
            array(
                array(
                    'common_name' => 'fkooman_ziptest',
                    'real_address' => '::ffff:91.64.87.183',
                    'bytes_in' => 127707,
                    'bytes_out' => 127903,
                    'connected_since' => 1450874955,
                    'virtual_address' => array(
                        '10.42.42.2',
                        'fd00:4242:4242::1000',
                    ),
                ),
                array(
                    'common_name' => 'sebas_tuxed_SGS6',
                    'real_address' => '::ffff:83.83.194.107',
                    'bytes_in' => 127229,
                    'bytes_out' => 180419,
                    'connected_since' => 1450872328,
                    'virtual_address' => array(
                        'fd00:4242:4242::1001',
                        '10.42.42.3',
                    ),
                ),
            ),
            $api->status()
        );
    }

    public function testStatusNoRoutingEntry()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_status_no_routing_entry.txt'));
        $api = new ServerApi($socket);
        $this->assertSame(
            array(
                array(
                    'common_name' => 'UNDEF',
                    'real_address' => '91.64.87.183:44756',
                    'bytes_in' => 0,
                    'bytes_out' => 0,
                    'connected_since' => 1450892816,
                    'virtual_address' => array(),
                ),
            ),
            $api->status()
        );
    }

    public function testKillSuccess()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_kill_success.txt'));
        $api = new ServerApi($socket);
        $this->assertTrue($api->kill('dummy'));
    }

    public function testKillError()
    {
        $socket = new TestSocket(self::readFile('openvpn_23_kill_error.txt'));
        $api = new ServerApi($socket);
        $this->assertFalse($api->kill('dummy'));
    }

    private static function readFile($fileName)
    {
        return @file_get_contents(
            sprintf(__DIR__.'/data/socket/%s', $fileName)
        );
    }
}
