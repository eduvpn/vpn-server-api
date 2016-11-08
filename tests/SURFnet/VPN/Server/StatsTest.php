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

namespace SURFnet\VPN\Server;

use PHPUnit_Framework_TestCase;
use DateTime;

class StatsTest extends PHPUnit_Framework_TestCase
{
    public function testOneClient()
    {
        $s = new Stats(new DateTime('2011-05-05'));
        $result = $s->get(
            [
                [
                    'common_name' => 'foo_bar',
                    'connected_at' => 1000000000,
                    'disconnected_at' => 1000000100,
                    'bytes_transferred' => 1000000,
                ],
            ]
        );
        $this->assertSame(
            [
                'days' => [
                    [
                        'number_of_connections' => 1,
                        'bytes_transferred' => 1000000,
                        'date' => '2001-09-09',
                        'unique_user_count' => 1,
                    ],
                ],
                'total_traffic' => 1000000,
                'generated_at' => 1304546400,
                'max_concurrent_connections' => 1,
                'unique_user_count' => 1,
                'active_user_count' => 0,
            ],
            $result
        );
    }

    public function testTwoConcurrentClients()
    {
        $s = new Stats(new DateTime('2011-05-05'));
        $result = $s->get(
            [
                [
                    'common_name' => 'foo_bar',
                    'connected_at' => 1000000000,
                    'disconnected_at' => 1000000100,
                    'bytes_transferred' => 1000000,
                ],
                [
                    'common_name' => 'bar_foo',
                    'connected_at' => 1000000000,
                    'disconnected_at' => 1000000100,
                    'bytes_transferred' => 1000000,
                ],
            ]
        );
        $this->assertSame(
            [
                'days' => [
                    [
                        'number_of_connections' => 2,
                        'bytes_transferred' => 2000000,
                        'date' => '2001-09-09',
                        'unique_user_count' => 2,
                    ],
                ],
                'total_traffic' => 2000000,
                'generated_at' => 1304546400,
                'max_concurrent_connections' => 2,
                'unique_user_count' => 2,
                'active_user_count' => 0,
            ],
            $result
        );
    }

    public function testTwoNonConcurrentClients()
    {
        $s = new Stats(new DateTime('2011-05-05'));
        $result = $s->get(
            [
                [
                    'common_name' => 'foo_bar',
                    'connected_at' => 1000000000,
                    'disconnected_at' => 1000000100,
                    'bytes_transferred' => 1000000,
                ],
                [
                    'common_name' => 'foo_bar',
                    'connected_at' => 1040000000,
                    'disconnected_at' => 1040000100,
                    'bytes_transferred' => 1000000,
                ],
            ]
        );
        $this->assertSame(
            [
                'days' => [
                    [
                        'number_of_connections' => 1,
                        'bytes_transferred' => 1000000,
                        'date' => '2001-09-09',
                        'unique_user_count' => 1,
                    ],
                    [
                        'number_of_connections' => 1,
                        'bytes_transferred' => 1000000,
                        'date' => '2002-12-16',
                        'unique_user_count' => 1,
                    ],
                ],
                'total_traffic' => 2000000,
                'generated_at' => 1304546400,
                'max_concurrent_connections' => 1,
                'unique_user_count' => 1,
                'active_user_count' => 0,
            ],
            $result
        );
    }

    public function testStillConnectedClient()
    {
        $s = new Stats(new DateTime('2011-05-05'));
        $result = $s->get(
            [
                [
                    'common_name' => 'foo_bar',
                    'connected_at' => 1000000000,
                    'disconnected_at' => null,
                    'bytes_transferred' => null,
                ],
            ]
        );
        $this->assertSame(
            [
                'days' => [
                    [
                        'number_of_connections' => 1,
                        'bytes_transferred' => 0,
                        'date' => '2001-09-09',
                        'unique_user_count' => 1,
                    ],
                ],
                'total_traffic' => 0,
                'generated_at' => 1304546400,
                'max_concurrent_connections' => 1,
                'unique_user_count' => 1,
                'active_user_count' => 1,
            ],
            $result
        );
    }
}
