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
    $dateTime = new DateTime();
    $configDir = sprintf('%s/config', $baseDir);
    $configFile = sprintf('%s/config.php', $configDir);
    $config = Config::fromFile($configFile);
    $logger = new Logger($argv[0]);
    $dataDir = sprintf('%s/data', $baseDir);
    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );

    if ($config->requireBool('useVpnDaemon', false)) {
        // with vpn-daemon
        $openVpnDaemonModule = new OpenVpnDaemonModule(
            $config,
            $storage,
            new DaemonSocket(sprintf('%s/vpn-daemon', $configDir), $config->requireBool('vpnDaemonTls', true))
        );
        $openVpnDaemonModule->setLogger($logger);
        foreach ($openVpnDaemonModule->getConnectionList(null, null) as $profileId => $connectionInfoList) {
            foreach ($connectionInfoList as $connectionInfo) {
                // check expiry of certificate
                $expiresAt = new DateTime($connectionInfo['valid_to']);
                if ($dateTime > $expiresAt) {
                    // certificate expired, disconnect!
                    $openVpnDaemonModule->killClient($connectionInfo['common_name']);
                }
            }
        }
    } else {
        // without vpn-daemon
        $serverManager = new ServerManager(
            $config,
            $logger,
            new ManagementSocket()
        );

        foreach ($serverManager->connections() as $profile) {
            foreach ($profile['connections'] as $connection) {
                // get information about the certificate based on commonName
                $commonName = $connection['common_name'];
                $userCertificateInfo = $storage->getUserCertificateInfo($commonName);
                if (false === $userCertificateInfo) {
                    // the certificate was not found (anymore), disconnect!
                    $serverManager->kill($commonName);
                    continue;
                }

                // check expiry of certificate
                $expiresAt = new DateTime($userCertificateInfo['valid_to']);
                if ($dateTime > $expiresAt) {
                    // certificate expired, disconnect!
                    $serverManager->kill($commonName);
                }
            }
        }
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
