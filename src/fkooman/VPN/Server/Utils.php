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

use RuntimeException;

class Utils
{
    public static function exec($cmd, $throwExceptionOnFailure = true)
    {
        exec($cmd, $output, $returnValue);

        if (0 !== $returnValue) {
            if ($throwExceptionOnFailure) {
                throw new RuntimeException(
                    sprintf('command "%s" did not complete successfully (%d)', $cmd, $returnValue)
                );
            }
        }
    }

    public static function writeTempConfig($tmpConfig, array $configFileData)
    {
        if (false === @file_put_contents($tmpConfig, implode(PHP_EOL, $configFileData))) {
            throw new RuntimeException('unable to write temporary config file');
        }
    }

    public static function normalizeIP($ipAddress)
    {
        return inet_ntop(inet_pton($ipAddress));
    }

    /**
     * Convert IPv4 CIDR address to IPv6 address with prefix containing the
     * IPv4 address with the same prefix.
     *
     * @param string $v6n the IPv6 network, e.g.: fd00:4242:4242:1194/64
     * @param string $v4n the IPv4 network, e.g. 10.42.42.0/24
     *
     * @return string the IPv6 address range containing the IPv4 CIDR, e.g.:
     *                fd00:4242:4242:1194:0:ffff:0a2a:2a00/120
     */
    public static function convert4to6($v6n, $v4n)
    {
        list($net4, $prefix4) = explode('/', $v4n);
        list($net6, $prefix6) = explode('/', $v6n);

        if ('64' !== $prefix6) {
            throw new RuntimeException('invalid IPv6 prefix, must be 64');
        }

        $prefix6 = 128 - (32 - $prefix4);
        $v4e = str_split(bin2hex(inet_pton($net4)), 4);

        return sprintf(
            '%s/%d',
            self::normalizeIP(
                sprintf('%s::ffff:%s:%s', $net6, $v4e[0], $v4e[1])
            ),
            $prefix6
        );
    }

    public static function getActiveLeases($leaseDir)
    {
        $activeLeases = [];
        foreach (glob(sprintf('%s/*', $leaseDir)) as $leaseFile) {
            $activeLeases[] = basename($leaseFile);
        }

        return $activeLeases;
    }

    public static function addRoute4($v4, $dev)
    {
        self::delRoute4($v4, false);
        self::flushRouteCache4();
        $cmd = sprintf('/usr/bin/sudo /sbin/ip -4 ro add %s/32 dev %s', $v4, $dev);
        self::exec($cmd);
    }

    public static function addRoute6($v6, $dev)
    {
        self::delRoute6($v6, false);
        self::flushRouteCache6();
        $cmd = sprintf('/usr/bin/sudo /sbin/ip -6 ro add %s/128 dev %s', $v6, $dev);
        self::exec($cmd);
    }

    public static function delRoute4($v4, $throwExceptionOnFailure = true)
    {
        $cmd = sprintf('/usr/bin/sudo /sbin/ip -4 ro del %s/32', $v4);
        self::exec($cmd, $throwExceptionOnFailure);
        self::flushRouteCache4();
    }

    public static function delRoute6($v6, $throwExceptionOnFailure = true)
    {
        $cmd = sprintf('/usr/bin/sudo /sbin/ip -6 ro del %s/128', $v6);
        self::exec($cmd, $throwExceptionOnFailure);
        self::flushRouteCache6();
    }

    private static function flushRouteCache4()
    {
        $cmd = '/usr/bin/sudo /sbin/ip -4 ro flush cache';
        self::exec($cmd, false);
    }

    private static function flushRouteCache6()
    {
        $cmd = '/usr/bin/sudo /sbin/ip -6 ro flush cache';
        self::exec($cmd, false);
    }
}
