#!/usr/bin/env php
<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;

$credentials = [
    'vpn-user-portal' => bin2hex(random_bytes(16)),
    'vpn-admin-portal' => bin2hex(random_bytes(16)),
    'vpn-server-node' => bin2hex(random_bytes(16)),
];

try {
    $p = new CliParser(
        'Update the API secrets used by the various components',
        [
            'instance' => ['the VPN instance', true, false],
            'prefix' => ['the prefix of the installed modules (DEFAULT: /usr/share)', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }
    $prefix = '/usr/share';
    if ($opt->hasItem('prefix')) {
        $prefix = $opt->getItem('prefix');
    }

    $instanceId = $opt->hasItem('instance') ? $opt->getItem('instance') : 'default';

    // api provider
    $configFile = sprintf('%s/vpn-server-api/config/%s/config.php', $prefix, $instanceId);
    $config = Config::fromFile($configFile);
    $configData = $config->toArray();
    $configData['apiConsumers']['vpn-user-portal'] = $credentials['vpn-user-portal'];
    $configData['apiConsumers']['vpn-admin-portal'] = $credentials['vpn-admin-portal'];
    $configData['apiConsumers']['vpn-server-node'] = $credentials['vpn-server-node'];
    Config::toFile($configFile, $configData, 0644);

    // consumers
    $consumerConfigFiles = [
        'vpn-user-portal' => sprintf('%s/vpn-user-portal/config/%s/config.php', $prefix, $instanceId),
        'vpn-admin-portal' => sprintf('%s/vpn-admin-portal/config/%s/config.php', $prefix, $instanceId),
        'vpn-server-node' => sprintf('%s/vpn-server-node/config/%s/config.php', $prefix, $instanceId),
    ];

    foreach ($consumerConfigFiles as $configId => $configFile) {
        if (@file_exists($configFile)) {
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
