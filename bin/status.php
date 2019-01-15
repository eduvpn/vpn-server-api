#!/usr/bin/env php
<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\OpenVpn\ManagementSocket;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Server\OpenVpn\ServerManager;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);

    $serverManager = new ServerManager(
        $config,
        new Logger($argv[0]),
        new ManagementSocket()
    );

    $output = [];
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
