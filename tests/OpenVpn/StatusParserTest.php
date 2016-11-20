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

namespace SURFnet\VPN\Server\OpenVpn;

require_once sprintf('%s/Test/TestSocket.php', __DIR__);

use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Server\OpenVpn\Test\TestSocket;

class StatusParserTest extends PHPUnit_Framework_TestCase
{
    public function testOpenVpn23()
    {
        $this->assertSame(
            [
                [
                    'common_name' => 'fkooman_testdroid',
                    'user_id' => 'fkooman',
                    'name' => 'testdroid',
                    'proto' => 6,
                    'virtual_address' => [
                        'fd77:6bac:e591:8203::1001',
                        '10.120.188.195',
                    ],
                ],
                [
                    'common_name' => 'fkooman_lenovo_f24',
                    'user_id' => 'fkooman',
                    'name' => 'lenovo_f24',
                    'proto' => 4,
                    'virtual_address' => [
                        '10.120.188.194',
                        'fd77:6bac:e591:8203::1000',
                    ],
                ],
            ],
            StatusParser::parse(explode("\n", file_get_contents(__DIR__.'/data/socket/openvpn_23_status.txt')))
        );
    }

    public function testOpenVpn24()
    {
        $this->assertSame(
            [
                [
                    'common_name' => 'fkooman_testdroid',
                    'user_id' => 'fkooman',
                    'name' => 'testdroid',
                    'proto' => 6,
                    'virtual_address' => [
                        'fd77:6bac:e591:8203::1001',
                        '10.120.188.195',
                    ],
                ],
                [
                    'common_name' => 'fkooman_lenovo_f24',
                    'user_id' => 'fkooman',
                    'name' => 'lenovo_f24',
                    'proto' => 4,
                    'virtual_address' => [
                        'fd77:6bac:e591:8203::1000',
                        '10.120.188.194',
                    ],
                ],
            ],
            StatusParser::parse(explode("\n", file_get_contents(__DIR__.'/data/socket/openvpn_24_status.txt')))
        );
    }
}
