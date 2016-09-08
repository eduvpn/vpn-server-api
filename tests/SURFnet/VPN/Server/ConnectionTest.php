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

class ConnectionTest extends PHPUnit_Framework_TestCase
{
    public function testConnect()
    {
        $c = new Connection(__DIR__);
        $c->connect(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'foo_xyz',
                'time_unix' => 1234567890,
                'ifconfig_pool_remote_ip' => '10.10.10.25',
                'ifconfig_pool_remote_ip6' => 'fd00:1234::25',
            ]
        );
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\ConnectionException
     * @expectedExceptionMessage client not allowed, user is disabled
     */
    public function testDisabledUser()
    {
        $c = new Connection(__DIR__);
        $c->connect(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'bar_xyz',
                'time_unix' => 1234567890,
                'ifconfig_pool_remote_ip' => '10.10.10.25',
                'ifconfig_pool_remote_ip6' => 'fd00:1234::25',
            ]
        );
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\ConnectionException
     * @expectedExceptionMessage client not allowed, CN is disabled
     */
    public function testDisabledCommonName()
    {
        $c = new Connection(__DIR__);
        $c->connect(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'internet',
                'common_name' => 'foo_disabled',
                'time_unix' => 1234567890,
                'ifconfig_pool_remote_ip' => '10.10.10.25',
                'ifconfig_pool_remote_ip6' => 'fd00:1234::25',
            ]
        );
    }

    public function testAclIsMember()
    {
        $c = new Connection(__DIR__);
        $c->connect(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'bar',
                'common_name' => 'foo_xyz',
                'time_unix' => 1234567890,
                'ifconfig_pool_remote_ip' => '10.10.10.25',
                'ifconfig_pool_remote_ip6' => 'fd00:1234::25',
            ]
        );
    }

    /**
     * @expectedException \SURFnet\VPN\Server\Exception\ConnectionException
     * @expectedExceptionMessage client not allowed, not a member of "all"
     */
    public function testAclIsNoMember()
    {
        $c = new Connection(__DIR__);
        $c->connect(
            [
                'INSTANCE_ID' => 'vpn.example',
                'POOL_ID' => 'bar',
                'common_name' => 'xyz_abc',
                'time_unix' => 1234567890,
                'ifconfig_pool_remote_ip' => '10.10.10.25',
                'ifconfig_pool_remote_ip6' => 'fd00:1234::25',
            ]
        );
    }
}
