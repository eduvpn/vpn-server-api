#!/usr/bin/php
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
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Server\InstanceConfig;
use SURFnet\VPN\Server\PoolConfig;

/**
 * Update the IP address configuration of vpn-server-api.
 *
 * IPv4:
 * Random value for the second and third octet, e.g: 10.53.129.0/24
 *
 * IPv6:
 * The IPv6 address is generated according to RFC 4193 (Global ID), it results
 * in a /60 network.
 */
function showHelp(array $argv)
{
    return implode(
        PHP_EOL,
        [
            sprintf('SYNTAX: %s [--instance vpn.example] [--pool internet] [--host hostname]', $argv[0]),
            '                   [--ext eth0]',
            '',
            '--instance instanceId      the instance to target, e.g. vpn.example',
            '--pool poolId              the pool to target, e.g. internet',
            '--host hostname           the hostname clients connect to',
            '--ext extIf                the external interface, e.g. eth0',
            '',
        ]
    );
}

try {
    $instanceId = null;
    $poolId = null;
    $extIf = null;
    $hostName = null;

    for ($i = 0; $i < $argc; ++$i) {
        if ('--help' == $argv[$i] || '-h' === $argv[$i]) {
            echo showHelp($argv);
            exit(0);
        }

        if ('--instance' === $argv[$i] || '-i' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $instanceId = $argv[$i + 1];
                ++$i;
            }
        }

        if ('--pool' === $argv[$i] || '-p' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $poolId = $argv[$i + 1];
                ++$i;
            }
        }

        if ('--host' === $argv[$i] || '-h' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $hostName = $argv[$i + 1];
                ++$i;
            }
        }

        if ('--ext' === $argv[$i] || '-e' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $extIf = $argv[$i + 1];
                ++$i;
            }
        }
    }

    if (is_null($instanceId)) {
        throw new RuntimeException('the instanceId must be specified, see --help');
    }

    if (is_null($poolId)) {
        throw new RuntimeException('the poolId must be specified, see --help');
    }

    if (is_null($extIf)) {
        throw new RuntimeException('the external interface must be specified, see --help');
    }

    if (is_null($hostName)) {
        $hostName = $instanceId;
    }

    $v4 = sprintf('10.%s.%s.0/24', hexdec(bin2hex(random_bytes(1))), hexdec(bin2hex(random_bytes(1))));
    $v6 = sprintf('fd%s:%s:%s:%s::/60', bin2hex(random_bytes(1)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2) & hex2bin('fff0')));

    echo sprintf('IPv4 CIDR  : %s', $v4).PHP_EOL;
    echo sprintf('IPv6 prefix: %s', $v6).PHP_EOL;

    $configFile = sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $instanceId);
    $instanceConfig = InstanceConfig::fromFile($configFile);
    $poolConfig = new PoolConfig($instanceConfig->v('vpnPools', $poolId));

    $instanceConfigData = $instanceConfig->v();
    $poolConfigData = $poolConfig->v();

    $poolConfigData['range'] = $v4;
    $poolConfigData['range6'] = $v6;
    $poolConfigData['hostName'] = $hostName;
    $poolConfigData['extIf'] = $extIf;

    $instanceConfigData['vpnPools'][$poolId] = $poolConfigData;

    InstanceConfig::toFile($configFile, $instanceConfigData);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
