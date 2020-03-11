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
use LC\Server\Api\OpenVpnDaemonModule;
use LC\Server\OpenVpn\DaemonSocket;
use LC\Server\OpenVpn\ServerManager;
use LC\Server\Storage;

try {
    $configDir = sprintf('%s/config', $baseDir);
    $configFile = sprintf('%s/config.php', $configDir);
    $config = Config::fromFile($configFile);
    $dataDir = sprintf('%s/data', $baseDir);
    $logger = new Logger($argv[0]);

    $showVerbose = false;
    foreach ($argv as $arg) {
        if ('--verbose' === $arg) {
            $showVerbose = true;
        }
    }

    if ($config->hasItem('useVpnDaemon') && $config->getItem('useVpnDaemon')) {
        // with vpn-daemon
        $storage = new Storage(
            new PDO(
                sprintf('sqlite://%s/db.sqlite', $dataDir)
            ),
            sprintf('%s/schema', $baseDir)
        );
        $openVpnDaemonModule = new OpenVpnDaemonModule(
            $config,
            $storage,
            new DaemonSocket(sprintf('%s/vpn-daemon', $configDir))
        );
        $openVpnDaemonModule->setLogger($logger);
        $output = '';
        foreach ($openVpnDaemonModule->getConnectionList(null, null) as $profileId => $connectionInfoList) {
            $output .= $profileId.','.count($connectionInfoList).PHP_EOL;
            if ($showVerbose) {
                foreach ($connectionInfoList as $connectionInfo) {
                    $output .= sprintf("%s\t%s\t%s", $profileId, $connectionInfo['common_name'], implode(', ', $connectionInfo['virtual_address'])).PHP_EOL;
                }
            }
        }

        echo $output;
    } else {
        // without vpn-daemon
        $serverManager = new ServerManager(
            $config,
            $logger,
            new ManagementSocket()
        );

        $output = '';
        foreach ($serverManager->connections() as $profile) {
            $output .= $profile['id'].','.count($profile['connections']).PHP_EOL;
            if ($showVerbose) {
                foreach ($profile['connections'] as $connection) {
                    $output .= sprintf("\t%s\t%s", $connection['common_name'], implode(', ', $connection['virtual_address'])).PHP_EOL;
                }
            }
        }

        echo $output;
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
