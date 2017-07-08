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
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Server\OpenVpn\ManagementSocket;
use SURFnet\VPN\Server\OpenVpn\ServerManager;

try {
    $p = new CliParser(
        'Get the connection status of an instance',
        [
            'instance' => ['the VPN instance', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->hasItem('instance') ? $opt->getItem('instance') : 'default';

    $configFile = sprintf('%s/config/%s/config.php', dirname(__DIR__), $instanceId);
    $config = Config::fromFile($configFile);

    $serverManager = new ServerManager(
        $config,
        new ManagementSocket(),
        new Logger($argv[0])
    );

    $output = [
        sprintf('*** %s ***', $instanceId),
    ];

    foreach ($serverManager->connections() as $profile) {
        $output[] = $profile['id'];

        foreach ($profile['connections'] as $connection) {
            $output[] = sprintf("\t%s\t%s", $connection['common_name'], implode(', ', $connection['virtual_address']));
        }
    }

    echo implode(PHP_EOL, $output).PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
