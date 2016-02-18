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

use PHPUnit_Framework_TestCase;
use PDO;

class ConnectionLogTest extends PHPUnit_Framework_TestCase
{
    /** @var ConnectionLog */
    private $connectionLog;

    public function setUp()
    {
        $io = $this->getMockBuilder('fkooman\IO\IO')->getMock();
        $io->method('getTime')->will($this->returnValue(12345678));

        $this->connectionLog = new ConnectionLog(new PDO('sqlite::memory:'), $io);
        $this->connectionLog->initDatabase();
    }

    public function testConnect()
    {
        $this->connectionLog->connect([
            'common_name' => 'foo_vpn_ex_def',
            'time_unix' => '1452535477',
            'v4' => '10.42.42.2',
            'v6' => 'fd00:4242:4242::2',
        ]);
    }

    public function testConnectDisconnect()
    {
        $this->connectionLog->connect([
            'common_name' => 'foo_vpn_ex_def',
            'time_unix' => '1452535477',
            'v4' => '10.42.42.2',
            'v6' => 'fd00:4242:4242::2',
        ]);
        $this->assertTrue(
            $this->connectionLog->disconnect([
                'common_name' => 'foo_vpn_ex_def',
                'time_unix' => '1452535477',
                'v4' => '10.42.42.2',
                'v6' => 'fd00:4242:4242::2',
                'bytes_received' => '4843',
                'bytes_sent' => '5317',
            ])
        );
    }

    public function testDisconnectWithoutMatchingConnect()
    {
        $this->assertFalse(
            $this->connectionLog->disconnect([
                'common_name' => 'foo_vpn_ex_def',
                'time_unix' => '1452535477',
                'v4' => '10.42.42.2',
                'v6' => 'fd00:4242:4242::2',
                'bytes_received' => '4843',
                'bytes_sent' => '5317',
            ])
        );
    }

    public function testGetConnectionList()
    {
        $this->connectionLog->connect([
            'common_name' => 'foo_vpn_ex_def',
            'time_unix' => '1452535477',
            'v4' => '10.42.42.2',
            'v6' => 'fd00:4242:4242::2',
        ]);
        $this->assertTrue(
            $this->connectionLog->disconnect([
                'common_name' => 'foo_vpn_ex_def',
                'time_unix' => '1452535477',
                'v4' => '10.42.42.2',
                'v6' => 'fd00:4242:4242::2',
                'bytes_received' => '4843',
                'bytes_sent' => '5317',
            ])
        );
        $this->assertSame(
            [
                [
                'common_name' => 'foo_vpn_ex_def',
                'time_unix' => '1452535477',
                'v4' => '10.42.42.2',
                'v6' => 'fd00:4242:4242::2',
                'bytes_received' => '4843',
                'bytes_sent' => '5317',
                'disconnect_time_unix' => '12345678',
                ],
            ],
            $this->connectionLog->getConnectionHistory(
                strtotime('today -31 days', 12345678),
                strtotime('tomorrow', 12345678)
            )
        );
    }
}
