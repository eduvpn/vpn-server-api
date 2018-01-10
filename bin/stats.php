#!/usr/bin/env php
<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$baseDir = dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once sprintf('%s/vendor/autoload.php', $baseDir);

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Server\Stats;
use SURFnet\VPN\Server\Storage;

try {
    $p = new CliParser(
        'Generate statistics for an instance',
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

    $dataDir = sprintf('%s/data/%s', $baseDir, $instanceId);
    $db = new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir));
    $storage = new Storage($db, new DateTime('now'));

    $outFile = sprintf('%s/stats.json', $dataDir);

    $stats = new Stats(new DateTime());
    $statsData = $stats->get($storage->getAllLogEntries());

    FileIO::writeJsonFile(
        $outFile,
        $statsData
    );
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
