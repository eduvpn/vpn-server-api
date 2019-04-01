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
use LC\Common\Logger;
use LC\OpenVpn\ManagementSocket;
use LC\Server\OpenVpn\ServerManager;
use LC\Server\Storage;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);

    $dataDir = sprintf('%s/data', $baseDir);
    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );

    $serverManager = new ServerManager(
        $config,
        new Logger($argv[0]),
        new ManagementSocket()
    );

    $output = [];
    foreach ($serverManager->connections() as $profile) {
        $output[] = $profile['id'];

        foreach ($profile['connections'] as $connection) {
            // get information about the certificate based on commonName
            $commonName = $connection['common_name'];
            if (false === $userCertificateInfo = $storage->getUserCertificateInfo($commonName)) {
                // XXX hmm?!
                continue;
            }
            $output[] = sprintf("\t%s\t%s [%s]", $commonName, implode(', ', $connection['virtual_address']), $userCertificateInfo['valid_to']);
        }
    }

    echo implode(PHP_EOL, $output).PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
