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
    $profileIdList = array_keys($config->requireArray('vpnProfiles'));

    $maxConcurrentConnectionLimitList = [];
    foreach ($profileIdList as $profileId) {
        $profileConfig = new ProfileConfig($config->s('vpnProfiles')->s($profileId));
        list($ipFour, $ipFourPrefix) = explode('/', $profileConfig->range());
        $vpnProtoPortsCount = count($profileConfig->vpnProtoPorts());
        $maxConcurrentConnectionLimitList[$profileId] = ((int) pow(2, 32 - (int) $ipFourPrefix)) - 3 * $vpnProtoPortsCount;
    }

    return $maxConcurrentConnectionLimitList;
}

/**
 * @return array
 */
function getProfilePortMapping(Config $config)
{
    $profileIdList = array_keys($config->requireArray('vpnProfiles'));
    $profilePortMapping = [];
    foreach ($profileIdList as $profileId) {
        $profileConfig = new ProfileConfig($config->s('vpnProfiles')->s($profileId));
        $profileNumber = $profileConfig->profileNumber();
        $vpnProtoPorts = $profileConfig->vpnProtoPorts();
        $profilePortMapping[$profileId] = ['vpnProtoPorts' => $vpnProtoPorts, 'profileNumber' => $profileNumber];
    }

    return $profilePortMapping;
}

/**
 * @return array
 */
function convertToProtoPort(array $profilePortMapping, array $portClientCount)
{
    $profileNumber = $profilePortMapping['profileNumber'];
    $vpnProtoPorts = $profilePortMapping['vpnProtoPorts'];
    $protoPortCount = [];
    foreach ($vpnProtoPorts as $k => $vpnProtoPort) {
        $managementPort = 11940 + OpenVpnDaemonModule::toPort($profileNumber, $k);
        $clientCount = 0;
        if (array_key_exists($managementPort, $portClientCount)) {
            $clientCount = $portClientCount[$managementPort];
        }
        $protoPortCount[$vpnProtoPort] = $clientCount;
    }

    return $protoPortCount;
}

/**
 * @return void
 */
function showHelp(array $argv)
{
    echo 'SYNTAX: '.$argv[0].\PHP_EOL.\PHP_EOL;
    echo '--json                use JSON output format'.\PHP_EOL;
    echo '--alert [percentage]  only show entries where IP space use is over specified'.\PHP_EOL;
    echo '                      percentage. The default percentage for --alert is 90 '.\PHP_EOL;
    echo '--connections         include connected clients (only with --json and when'.\PHP_EOL;
    echo '                      using vpn-daemon)'.\PHP_EOL;
}

/**
 * @param bool $asJson
 *
 * @return void
 */
function outputConversion(array $outputData, $asJson)
{
    // JSON
    if ($asJson) {
        echo json_encode($outputData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

        return;
    }

    // CSV
    if (0 === count($outputData)) {
        return;
    }
    $headerKeys = array_keys($outputData[0]);
    echo implode(',', $headerKeys).\PHP_EOL;
    foreach ($outputData as $outputRow) {
        echo implode(',', array_values($outputRow)).\PHP_EOL;
    }
}

try {
    $configDir = sprintf('%s/config', $baseDir);
    $configFile = sprintf('%s/config.php', $configDir);
    $config = Config::fromFile($configFile);
    $dataDir = sprintf('%s/data', $baseDir);
    $logger = new Logger($argv[0]);

    $alertOnly = false;
    $asJson = false;
    $alertPercentage = 90;
    $includeConnections = false;    // only for JSON
    $searchForPercentage = false;
    $showHelp = false;
    foreach ($argv as $arg) {
        if ('--alert' === $arg) {
            $alertOnly = true;
            $searchForPercentage = true;
            continue;
        }
        if ($searchForPercentage) {
            // capture parameter after "--alert" and use that as percentage
            if (is_numeric($arg) && 0 <= $arg && 100 >= $arg) {
                $alertPercentage = (int) $arg;
            }
            $searchForPercentage = false;
        }
        if ('--json' === $arg) {
            $asJson = true;
        }
        if ('--connections' === $arg) {
            $includeConnections = true;
        }
        if ('--help' === $arg || '-h' === $arg || '-help' === $arg) {
            $showHelp = true;
        }
    }

    if ($showHelp) {
        showHelp($argv);

        return;
    }

    $maxClientLimit = getMaxClientLimit($config);

    if ($config->requireBool('useVpnDaemon', false)) {
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
            new DaemonSocket(sprintf('%s/vpn-daemon', $configDir), $config->requireBool('vpnDaemonTls', true))
        );
        $openVpnDaemonModule->setLogger($logger);
        $outputData = [];
        foreach ($openVpnDaemonModule->getConnectionList(null, null) as $profileId => $connectionInfoList) {
            // extract only stuff we need
            $displayConnectionInfo = [];
            $portClientCount = [];
            foreach ($connectionInfoList as $connectionInfo) {
                $managementPort = $connectionInfo['management_port'];
                if (!array_key_exists($managementPort, $portClientCount)) {
                    $portClientCount[$managementPort] = 0;
                }
                ++$portClientCount[$connectionInfo['management_port']];

                $displayConnectionInfo[] = [
                    'user_id' => $connectionInfo['user_id'],
                    'virtual_address' => $connectionInfo['virtual_address'],
                ];
            }
            $activeConnectionCount = count($displayConnectionInfo);
            $profileMaxClientLimit = $maxClientLimit[$profileId];
            $percentInUse = floor($activeConnectionCount / $profileMaxClientLimit * 100);
            if ($alertOnly && $alertPercentage > $percentInUse) {
                continue;
            }
            $outputRow = [
                'profile_id' => $profileId,
                'active_connection_count' => $activeConnectionCount,
                'max_connection_count' => $profileMaxClientLimit,
                'percentage_in_use' => $percentInUse,
            ];
            if ($asJson) {
                $outputRow['port_client_count'] = convertToProtoPort(getProfilePortMapping($config)[$profileId], $portClientCount);
                if ($includeConnections) {
                    $outputRow['connection_list'] = $displayConnectionInfo;
                }
            }
            $outputData[] = $outputRow;
        }
        outputConversion($outputData, $asJson);
    } else {
        // without vpn-daemon, no list of connected users
        $serverManager = new ServerManager(
            $config,
            $logger,
            new ManagementSocket()
        );

        $outputData = [];
        foreach ($serverManager->connections() as $profile) {
            $activeConnectionCount = count($profile['connections']);
            $profileMaxClientLimit = $maxClientLimit[$profile['id']];
            $percentInUse = floor($activeConnectionCount / $profileMaxClientLimit * 100);
            if ($alertOnly && 90 > $percentInUse) {
                continue;
            }
            $outputRow = [
                'profile_id' => $profile['id'],
                'active_connection_count' => $activeConnectionCount,
                'max_connection_count' => $profileMaxClientLimit,
                'percentage_in_use' => $percentInUse,
            ];
            $outputData[] = $outputRow;
        }
        outputConversion($outputData, $asJson);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;
    exit(1);
}
