#!/usr/bin/php
<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Parse the systemd journal.
 *
 * Usage:
 *
 * $ sudo journalctl \
 *     -o json \
 *     -t vpn-server-api-client-connect \
 *     -t vpn-server-api-client-disconnect \
 *     | vpn-server-api-parse-journal
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Common\FileIO;

/*
 * Due to not wanting to store all user data forever, we have a log window
 * after which log entries disappear from the system, this can result in some
 * issues. The log window is typically 1 month.
 *
 * LIMITATIONS:
 *
 * - if a client connected before this logging window started, it is not
 *   visible in the logs;
 * - if a client stays connected for the whole logging window, it is not
 *   available in the log at all;
 * - if a client was connected before the logging window started and
 *   disconnected during, it is not added to the log;
 */

$clientConnectSyslogIdentifier = 'vpn-server-node-client-connect';
$clientDisconnectSyslogIdentifier = 'vpn-server-node-client-disconnect';

function verifyMessage(array $messageData, $type)
{
    $requiredKeys = [
        'INSTANCE_ID',
        'PROFILE_ID',
        'common_name',
        'time_unix',
        'ifconfig_pool_remote_ip',
        'ifconfig_pool_remote_ip6',
    ];

    if ('disconnect' === $type) {
        $requiredKeys[] = 'bytes_received';
        $requiredKeys[] = 'bytes_sent';
        $requiredKeys[] = 'time_duration';
    }

    foreach ($requiredKeys as $k) {
        if (!array_key_exists($k, $messageData)) {
            return false;
        }
    }

    return true;
}

try {
    $logData = [];

    // every line is a JSON object
    while ($jsonLine = fgets(STDIN)) {
        $jsonData = json_decode($jsonLine, true);

        if ($clientConnectSyslogIdentifier === $jsonData['SYSLOG_IDENTIFIER']) {
            // handle connect data
            $message = $jsonData['MESSAGE'];
            $messageData = json_decode($message, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                // XXX if an error occurred decoding the message, it was
                // probably a log error message, ignore them for now, but later we
                // will need them as well!
                continue;
            }

            if (!verifyMessage($messageData, 'connect')) {
                continue;
            }

            $instanceId = $messageData['INSTANCE_ID'];
            $profileId = $messageData['PROFILE_ID'];
            $commonName = $messageData['common_name'];
            $userId = explode('_', $commonName, 2)[0];
            $configName = explode('_', $commonName, 2)[1];

            $logKey = sprintf('%s:%s:%s', $profileId, $messageData['common_name'], $messageData['time_unix']);
            $logData[$instanceId][$logKey] = [
                'profile_id' => $profileId,
                'user_id' => $userId,
                'config_name' => $configName,
                'v4' => $messageData['ifconfig_pool_remote_ip'],
                'v6' => $messageData['ifconfig_pool_remote_ip6'],
                'connect_time' => intval($messageData['time_unix']),
            ];
        }

        if ($clientDisconnectSyslogIdentifier === $jsonData['SYSLOG_IDENTIFIER']) {
            // handle disconnect data
            $message = $jsonData['MESSAGE'];
            $messageData = json_decode($message, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                // XXX if an error occurred decoding the message, it was
                // probably a log error message, ignore them for now, but later we
                // will need them as well!
                continue;
            }

            if (!verifyMessage($messageData, 'disconnect')) {
                continue;
            }

            $instanceId = $messageData['INSTANCE_ID'];
            $profileId = $messageData['PROFILE_ID'];
            $logKey = sprintf('%s:%s:%s', $profileId, $messageData['common_name'], $messageData['time_unix']);
            // XXX what if instanceId key does not exist?
            if (!array_key_exists($logKey, $logData[$instanceId])) {
                // XXX we did not find a matching connect entry...
                // just ignore it
                continue;
            }
            $dataTransferred = $messageData['bytes_sent'] + $messageData['bytes_received'];
            $logData[$instanceId][$logKey] = array_merge(
                $logData[$instanceId][$logKey],
                [
                    'disconnect_time' => $messageData['time_unix'] + intval($messageData['time_duration']),
                    'traffic' => $dataTransferred,
                ]
            );
        }
    }

    foreach ($logData as $instanceId => $logEntries) {
        $logFile = sprintf('%s/data/%s/log.json', dirname(__DIR__), $instanceId);
        $logDir = dirname($logFile);

        FileIO::createDir($logDir, 0711);
        FileIO::writeJsonFile(
            $logFile,
            [
                'entries' => array_values($logEntries),
            ],
            0644
        );
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
