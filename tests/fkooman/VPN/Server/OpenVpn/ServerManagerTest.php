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
use fkooman\Json\Json;

class ServerManagerTest extends PHPUnit_Framework_TestCase
{
    public function testVersionNoServers()
    {
        $m = new ServerManager();
        $this->assertSame(
            array(
                'items' => array(
                ),
            ),
            $m->version()
        );
    }

    public function testUnableToConnect()
    {
        $m = new ServerManager();
        $serverOne = new ServerApi('one', new TestSocket(self::readFile('openvpn_23_version.txt')));
        // serverTwo is not available
        $serverTwo = new ServerApi('two', new TestSocket(self::readFile('openvpn_23_version.txt'), true));
        $m->addServer($serverOne);
        $m->addServer($serverTwo);
        $this->assertSame(
            array(
                'items' => array(
                    array(
                        'id' => 'one',
                        'ok' => true,
                        'version' => 'OpenVPN 2.3.8 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Aug  4 2015',
                    ),
                    array(
                        'id' => 'two',
                        'ok' => false,
                    ),
                ),
            ),
            $m->version()
        );
    }

    public function testLoadStatsOneServer()
    {
        $m = new ServerManager();
        $serverOne = new ServerApi('one', new TestSocket(self::readFile('openvpn_23_load_stats.txt')));
        $m->addServer($serverOne);

        $this->assertSame(
            array(
                'items' => array(
                    array(
                        'id' => 'one',
                        'ok' => true,
                        'load-stats' => array(
                            'number_of_clients' => 0,
                            'bytes_in' => 2224463,
                            'bytes_out' => 6102370,
                        ),
                    ),
                ),
            ),
            $m->loadStats()
        );
    }

    public function testStatusTwoServers()
    {
        $m = new ServerManager();
        $serverOne = new ServerApi('one', new TestSocket(self::readFile('openvpn_23_status_one_client.txt')));
        $serverTwo = new ServerApi('two', new TestSocket(self::readFile('openvpn_23_status_two_clients.txt')));
        $m->addServer($serverOne);
        $m->addServer($serverTwo);
        $this->assertSame(
            '{"items":[{"id":"one","ok":true,"status":[{"common_name":"fkooman_samsung_i9300","real_address":"91.64.87.183:43103","bytes_in":18301,"bytes_out":30009,"connected_since":1451323167,"virtual_address":["fd00:4242:4242::1003","10.42.42.5"]}]},{"id":"two","ok":true,"status":[{"common_name":"fkooman_ziptest","real_address":"::ffff:91.64.87.183","bytes_in":127707,"bytes_out":127903,"connected_since":1450874955,"virtual_address":["10.42.42.2","fd00:4242:4242::1000"]},{"common_name":"sebas_tuxed_SGS6","real_address":"::ffff:83.83.194.107","bytes_in":127229,"bytes_out":180419,"connected_since":1450872328,"virtual_address":["fd00:4242:4242::1001","10.42.42.3"]}]}]}',
            Json::encode($m->status())
        );
    }

    public function testKillThreeServers()
    {
        $m = new ServerManager();
        $serverOne = new ServerApi('one', new TestSocket(self::readFile('openvpn_23_kill_success.txt')));
        $serverTwo = new ServerApi('two', new TestSocket(self::readFile('openvpn_23_kill_error.txt')));
        $serverThree = new ServerApi('three', new TestSocket(self::readFile('openvpn_23_kill_error.txt')));

        $m->addServer($serverOne);
        $m->addServer($serverTwo);
        $m->addServer($serverThree);
        $this->assertSame(
            '{"items":[{"id":"one","ok":true,"kill":true},{"id":"two","ok":true,"kill":false},{"id":"three","ok":true,"kill":false}]}',
            Json::encode($m->kill('foo'))
        );
    }

    private static function readFile($fileName)
    {
        return @file_get_contents(
            sprintf(__DIR__.'/data/socket/%s', $fileName)
        );
    }
}
