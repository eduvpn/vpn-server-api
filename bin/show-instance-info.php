#!/usr/bin/env php
<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
$baseDir = dirname(__DIR__);

// find the autoloader (package installs, composer)
foreach (['src', 'vendor'] as $autoloadDir) {
    if (@file_exists(sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir))) {
        require_once sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir);
        break;
    }
}

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;

try {
    $configDir = sprintf('%s/config', $baseDir);

    $p = new CliParser(
        'Show Instance and Profile Information',
        [
            'free-instance-number' => ['show the first free instanceNumber', false, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceList = [];
    $instanceNumberList = [];

    foreach (glob(sprintf('%s/*', $configDir), GLOB_ONLYDIR) as $dirName) {
        $instanceId = basename($dirName);

        $configFile = sprintf('%s/%s/config.php', $configDir, $instanceId);
        $config = Config::fromFile($configFile);

        $instanceNumber = $config->getItem('instanceNumber');
        $instanceNumberList[] = $instanceNumber;
        $instanceList[$instanceId] = [
            'instanceNumber' => $instanceNumber,
            'profileInfo' => [],
        ];

        $vpnProfiles = $config->getSection('vpnProfiles');
        $profileIdList = array_keys($vpnProfiles->toArray());
        foreach ($profileIdList as $profileId) {
            $profileNumber = $vpnProfiles->getSection($profileId)->getItem('profileNumber');
            $instanceList[$instanceId]['profileInfo'][] = [
                'profileId' => $profileId,
                'profileNumber' => $profileNumber,
            ];
        }
    }

    if ($opt->hasItem('free-instance-number')) {
        sort($instanceNumberList);
        echo end($instanceNumberList) + 1 .PHP_EOL;
        exit(0);
    }

    foreach ($instanceList as $k => $v) {
        echo sprintf('[%d] (%s)', $v['instanceNumber'], $k).PHP_EOL;
        foreach ($v['profileInfo'] as $p) {
            echo "\t".sprintf('[%d] (%s)', $p['profileNumber'], $p['profileId']).PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
