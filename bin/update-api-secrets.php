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

$credentials = [
    'vpn-user-portal' => bin2hex(random_bytes(16)),
    'vpn-server-node' => bin2hex(random_bytes(16)),
];

try {
    $prefix = '/usr/share';
    for ($i = 1; $i < $argc; ++$i) {
        if ('--prefix' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $prefix = $argv[$i + 1];
            }
            continue;
        }
        if ('--help' === $argv[$i]) {
            echo 'SYNTAX: '.$argv[0].' [--prefix PREFIX]'.PHP_EOL;
            exit(0);
        }
    }

    // api provider
    $configFile = sprintf('%s/vpn-server-api/config/config.php', $prefix);
    $config = Config::fromFile($configFile);
    $configData = $config->toArray();
    $configData['apiConsumers']['vpn-user-portal'] = $credentials['vpn-user-portal'];
    $configData['apiConsumers']['vpn-server-node'] = $credentials['vpn-server-node'];
    Config::toFile($configFile, $configData, 0644);

    // consumers
    $consumerConfigFiles = [
        'vpn-user-portal' => sprintf('%s/vpn-user-portal/config/config.php', $prefix),
        'vpn-server-node' => sprintf('%s/vpn-server-node/config/config.php', $prefix),
    ];

    foreach ($consumerConfigFiles as $configId => $configFile) {
        if (FileIO::exists($configFile)) {
            $config = Config::fromFile($configFile);
            $configData = $config->toArray();
            $configData['apiPass'] = $credentials[$configId];
            Config::toFile($configFile, $configData, 0644);
        }
    }
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
