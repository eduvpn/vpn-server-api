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
use LC\Common\ProfileConfig;
use LC\OpenVpn\ManagementSocket;
use LC\Server\Api\OpenVpnDaemonModule;
use LC\Server\OpenVpn\DaemonSocket;
use LC\Server\OpenVpn\ServerManager;
use LC\Server\Storage;

/**
 * @return array
 */
function getMaxClientLimit(Config $config)
{
    $profileIdList = array_keys($config->getItem('vpnProfiles'));

    $maxConcurrentConnectionLimitList = [];
    foreach ($profileIdList as $profileId) {
        $profileConfig = new ProfileConfig($config->getSection('vpnProfiles')->getItem($profileId));
        list($ipFour, $ipFourPrefix) = explode('/', $profileConfig->getItem('range'));
        $vpnProtoPortsCount = count($profileConfig->getItem('vpnProtoPorts'));
        $maxConcurrentConnectionLimitList[$profileId] = ((int) pow(2, 32 - (int) $ipFourPrefix)) - 3 * $vpnProtoPortsCount;
    }

    return $maxConcurrentConnectionLimitList;
}

try {
    $configDir = sprintf('%s/config', $baseDir);
    $configFile = sprintf('%s/config.php', $configDir);
    $config = ProfileConfig::fromFile($configFile);
    $dataDir = sprintf('%s/data', $baseDir);
    $logger = new Logger($argv[0]);

    $alertOnly = false;
    foreach ($argv as $arg) {
        if ('--alert' === $arg) {
            $alertOnly = true;
        }
    }

    $maxClientLimit = getMaxClientLimit($config);

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
            $activeConnectionCount = count($connectionInfoList);
            $profileMaxClientLimit = $maxClientLimit[$profileId];
            $percentInUse = floor($activeConnectionCount / $profileMaxClientLimit * 100);
            if (!$alertOnly || ($alertOnly && 90 <= $percentInUse)) {
                $output .= $profileId.','.$activeConnectionCount.','.$profileMaxClientLimit.','.$percentInUse.'%'.PHP_EOL;
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
            $activeConnectionCount = count($connectionInfoList);
            $profileMaxClientLimit = $maxClientLimit[$profileId];
            $percentInUse = floor($activeConnectionCount / $profileMaxClientLimit * 100);
            if (!$alertOnly || ($alertOnly && 90 <= $percentInUse)) {
                $output .= $profile['id'].','.$activeConnectionCount.','.$profileMaxClientLimit.','.$percentInUse.'%'.PHP_EOL;
            }
        }

        echo $output;
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
