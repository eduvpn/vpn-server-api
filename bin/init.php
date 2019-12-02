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
use LC\Server\CA\EasyRsaCa;
use LC\Server\CA\VpnCa;
use LC\Server\Storage;

try {
    $dataDir = sprintf('%s/data', $baseDir);
    $configDir = sprintf('%s/config', $baseDir);

    $config = Config::fromFile(
        sprintf('%s/config.php', $configDir)
    );

    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);
    $vpnCaDir = sprintf('%s/ca', $dataDir);

    if (null === $vpnCaPath = $config->optionalItem('vpnCaPath')) {
        // we want to use (legacy) EasyRsaCa
        $ca = new EasyRsaCa($easyRsaDir, $easyRsaDataDir);
    } else {
        // we want to use VpnCA
        // VpnCa gets the easyRsaDataDir in case a migration is needed...
        $ca = new VpnCa($vpnCaDir, $vpnCaPath, $easyRsaDataDir);
    }

    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );
    $storage->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
