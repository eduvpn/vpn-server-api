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

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Server\Storage;

try {
    $p = new CliParser(
        'Initialize the CA',
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

    $configFile = sprintf('%s/config/%s/config.php', $baseDir, $instanceId);
    $config = Config::fromFile($configFile);

    $dataDir = sprintf('%s/data/%s', $baseDir, $instanceId);
    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir),
        new DateTime('now')
    );
    $storage->update();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
