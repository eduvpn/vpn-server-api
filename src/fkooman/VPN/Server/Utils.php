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

use fkooman\Http\Exception\BadRequestException;
use RuntimeException;

class Utils
{
    public static function validateCommonName($commonName)
    {
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $commonName)) {
            throw new BadRequestException('invalid characters in common name');
        }

        // MUST NOT be '..'
        if ('..' === $commonName) {
            throw new BadRequestException('common name cannot be ".."');
        }
    }

    public static function validateUserId($userId)
    {
        if (0 === preg_match('/^[a-zA-Z0-9-_.@]+$/', $userId)) {
            throw new BadRequestException('invalid characters in userId');
        }
    }

    public static function validateAddress($ipAddress)
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new BadRequestException('invalid v4 address');
        }
    }

    /**
     * Validate that the date is in YYYY-MM-DD format.
     *
     * @param string $dateString the date in YYYY-MM-DD format
     */
    public static function validateDate($dateString)
    {
        if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $dateString)) {
            throw new BadRequestException('invalid date format');
        }
    }

    public static function exec($cmd)
    {
        exec($cmd, $output, $returnValue);

        if (0 !== $returnValue) {
            throw new RuntimeException(
                sprintf('command "%s" did not complete successfully (%d)', $cmd, $returnValue)
            );
        }
    }

    public static function writeTempConfig($tmpConfig, array $configFileData)
    {
        if (false === @file_put_contents($tmpConfig, implode(PHP_EOL, $configFileData))) {
            throw new RuntimeException('unable to write temporary config file');
        }
    }

    public static function getUsedIpList($ipPoolDir)
    {
        $usedIpList = [];
        foreach (glob(sprintf('%s/*', $ipPoolDir)) as $ipFile) {
            $usedIpList[] = basename($ipFile);
        }

        return $usedIpList;
    }

    public static function addRoute4($v4, $dev)
    {
        $cmd = sprintf('/usr/bin/sudo /sbin/ip -4 ro add %s/32 dev %s', $v4, $dev);
        self::exec($cmd);
    }

    public static function addRoute6($v6, $dev)
    {
        $cmd = sprintf('/usr/bin/sudo /sbin/ip -6 ro add %s/128 dev %s', $v6, $dev);
        self::exec($cmd);
    }

    public static function delRoute4($v4, $dev)
    {
        try {
            $cmd = sprintf('/usr/bin/sudo /sbin/ip -4 ro del %s/32 dev %s', $v4, $dev);
            self::exec($cmd);
        } catch (RuntimeException $e) {
            // not the end of the world if the route cannot be deleted
        }
    }

    public static function delRoute6($v6, $dev)
    {
        try {
            $cmd = sprintf('/usr/bin/sudo /sbin/ip -6 ro del %s/128 dev %s', $v6, $dev);
            self::exec($cmd);
        } catch (RuntimeException $e) {
            // not the end of the world if the route cannot be deleted
        }
    }
}
