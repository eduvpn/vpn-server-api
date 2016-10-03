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
use SURFnet\VPN\Common\CliParser;

/*
 * Update the IP address configuration of vpn-server-api.
 *
 * IPv4:
 * Random value for the second and third octet, e.g: 10.53.129.0/24
 *
 * IPv6:
 * The IPv6 address is generated according to RFC 4193 (Global ID), it results
 * in a /60 network.
 */

try {
    $p = new CliParser(
        'Automatically generate an IP address and basic config for a pool',
        [
            'instance' => ['the instance to target, e.g. vpn.example', true, true],
            'pool' => ['the pool to target, e.g. internet', true, true],
            'host' => ['the hostname clients connect to', true, true],
            'ext' => ['the external interface, e.g. eth0', true, true],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $v4 = sprintf('10.%s.%s.0/24', hexdec(bin2hex(random_bytes(1))), hexdec(bin2hex(random_bytes(1))));
    $v6 = sprintf('fd%s:%s:%s:%s::/60', bin2hex(random_bytes(1)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2) & hex2bin('fff0')));

    echo sprintf('IPv4 CIDR  : %s', $v4).PHP_EOL;
    echo sprintf('IPv6 prefix: %s', $v6).PHP_EOL;

    $configFile = sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $opt->v('instance'));
    $instanceConfig = InstanceConfig::fromFile($configFile);
    $poolConfig = new PoolConfig($instanceConfig->v('vpnPools', $opt->v('pool')));

    $instanceConfigData = $instanceConfig->v();
    $poolConfigData = $poolConfig->v();

    $poolConfigData['range'] = $v4;
    $poolConfigData['range6'] = $v6;
    $poolConfigData['hostName'] = $opt->v('host');
    $poolConfigData['extIf'] = $opt->v('ext');

    $instanceConfigData['vpnPools'][$opt->v('pool')] = $poolConfigData;

    InstanceConfig::toFile($configFile, $instanceConfigData);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
