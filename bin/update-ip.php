<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\ProfileConfig;

/*
 * Update the IP address configuration of vpn-server-api.
 *
 * IPv4:
 * Random value for the second and third octet, e.g: 10.53.129.0/25
 *
 * IPv6:
 * The IPv6 address is generated according to RFC 4193 (Global ID), it results
 * in a /64 network.
 */

try {
    $profileId = null;
    $hostName = null;
    for ($i = 1; $i < $argc; ++$i) {
        if ('--profile' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $profileId = $argv[$i + 1];
            }
            continue;
        }
        if ('--host' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $hostName = $argv[$i + 1];
            }
            continue;
        }
        if ('--help' === $argv[$i]) {
            echo 'SYNTAX: '.$argv[0].' --profile PROFILE_ID --host HOSTNAME'.PHP_EOL;
            exit(0);
        }
    }

    if (null === $profileId || null === $hostName) {
        echo 'SYNTAX: '.$argv[0].' --profile PROFILE_ID --host HOSTNAME'.PHP_EOL;
        exit(1);
    }

    $v4 = sprintf(
        '10.%s.%s.0/25',
        hexdec(bin2hex(random_bytes(1))),
        hexdec(bin2hex(random_bytes(1)))
    );

    $v6 = sprintf(
        'fd%s:%s:%s:%s::/64',
        bin2hex(random_bytes(1)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2))
    );

    // figure out DNS based on `/etc/resolv.conf`
    $nameServerList = [];
    if (FileIO::exists('/etc/resolv.conf')) {
        $resolvConf = FileIO::readFile('/etc/resolv.conf');
        $resolvConfData = explode(PHP_EOL, $resolvConf);
        foreach ($resolvConfData as $revolvConfLine) {
            if (0 === strpos(trim($revolvConfLine), 'nameserver ')) {
                // found a nameserver
                $nameServerIp = trim(substr($revolvConfLine, 11));
                // ignore "local" addresses
                if (0 === strpos($nameServerIp, '127.')) {
                    continue;
                }
                if (0 === strpos($nameServerIp, '::1')) {
                    continue;
                }
                $nameServerList[] = trim(substr($revolvConfLine, 11));
            }
        }
    }
    if (0 === count($nameServerList)) {
        $nameServerList = [
            '9.9.9.9',
            '2620:fe::fe',
        ];
    }

    echo sprintf('IPv4 CIDR  : %s', $v4).PHP_EOL;
    echo sprintf('IPv6 prefix: %s', $v6).PHP_EOL;
    echo sprintf('DNS        : %s', implode(', ', $nameServerList)).PHP_EOL;

    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);
    $profileConfig = new ProfileConfig($config->s('vpnProfiles')->requireArray($profileId));

    $configData = $config->toArray();
    $profileConfigData = $profileConfig->toArray();

    $profileConfigData['range'] = $v4;
    $profileConfigData['range6'] = $v6;
    $profileConfigData['dns'] = $nameServerList;
    $profileConfigData['hostName'] = $hostName;
    $configData['vpnProfiles'][$profileId] = $profileConfigData;

    Config::toFile($configFile, $configData, 0644);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
