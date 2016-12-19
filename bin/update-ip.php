#!/usr/bin/env php
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

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\ProfileConfig;

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
        'Automatically generate an IP address and basic config for a profile',
        [
            'instance' => ['the VPN instance', true, false],
            'profile' => ['the profile to target, e.g. internet', true, true],
            'host' => ['the hostname clients connect to', true, true],
            'ext' => ['the external interface, e.g. eth0', true, true],
            'reject4' => ['do not forward IPv4 traffic', false, false],
            'reject6' => ['do not forward IPv6 traffic', false, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->e('instance') ? $opt->v('instance') : 'default';

    $v4 = sprintf('10.%s.%s.0/24', hexdec(bin2hex(random_bytes(1))), hexdec(bin2hex(random_bytes(1))));
    $v6 = sprintf('fd%s:%s:%s:%s::/60', bin2hex(random_bytes(1)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2) & hex2bin('fff0')));

    echo sprintf('IPv4 CIDR  : %s', $v4).PHP_EOL;
    echo sprintf('IPv6 prefix: %s', $v6).PHP_EOL;

    $configFile = sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $instanceId);
    $config = Config::fromFile($configFile);
    $profileConfig = new ProfileConfig($config->v('vpnProfiles', $opt->v('profile')));

    $configData = $config->v();
    $profileConfigData = $profileConfig->v();

    $profileConfigData['range'] = $v4;
    $profileConfigData['range6'] = $v6;
    $profileConfigData['hostName'] = $opt->v('host');
    $profileConfigData['extIf'] = $opt->v('ext');
    if ($opt->e('reject4')) {
        $profileConfigData['reject4'] = true;
    }
    if ($opt->e('reject6')) {
        $profileConfigData['reject6'] = true;
    }
    $configData['vpnProfiles'][$opt->v('profile')] = $profileConfigData;

    Config::toFile($configFile, $configData, 0644);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
