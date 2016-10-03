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
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Server\CliParser;

try {
    $p = new CliParser(
        'Generate statistics for an instance',
        [
            'instance' => ['the instance', true, true],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $opt->v('instance'));
    $inFile = sprintf('%s/log.json', $dataDir);
    $outFile = sprintf('%s/stats.json', $dataDir);

    $statsData = [];

    $logData = FileIO::readJsonFile($inFile);

    $timeConnection = [];
    $uniqueUsers = [];

    foreach ($logData['entries'] as $entry) {
        $dateOfConnection = date('Y-m-d', $entry['connect_time']);
        if (!array_key_exists($dateOfConnection, $statsData)) {
            $statsData[$dateOfConnection] = [];
        }
        if (!array_key_exists('number_of_connections', $statsData[$dateOfConnection])) {
            $statsData[$dateOfConnection]['number_of_connections'] = 0;
        }
        if (!array_key_exists('traffic', $statsData[$dateOfConnection])) {
            $statsData[$dateOfConnection]['traffic'] = 0;
        }
        if (!array_key_exists('user_list', $statsData[$dateOfConnection])) {
            $statsData[$dateOfConnection]['user_list'] = [];
        }

        ++$statsData[$dateOfConnection]['number_of_connections'];
        if (array_key_exists('traffic', $entry)) {
            // when client is still connected, it won't have a 'traffic' entry
            $statsData[$dateOfConnection]['traffic'] += $entry['traffic'];

            $connectTime = $entry['connect_time'];
            $disconnectTime = $entry['disconnect_time'];

            // add it to table to be able to determine max concurrent connection
            // count
            if (!array_key_exists($connectTime, $timeConnection)) {
                $timeConnection[$connectTime] = [];
            }
            $timeConnection[$connectTime][] = 'C';

            if (!array_key_exists($disconnectTime, $timeConnection)) {
                $timeConnection[$disconnectTime] = [];
            }
            $timeConnection[$disconnectTime][] = 'D';
        }
        if (!in_array($entry['user_id'], $statsData[$dateOfConnection]['user_list'])) {
            $statsData[$dateOfConnection]['user_list'][] = $entry['user_id'];
        }

        // global unique user list
        if (!in_array($entry['user_id'], $uniqueUsers)) {
            $uniqueUsers[] = $entry['user_id'];
        }
    }

    ksort($timeConnection);
    $firstEntryTime = intval(key($timeConnection));
    end($timeConnection);
    $lastEntryTime = intval(key($timeConnection));
    reset($timeConnection);

    $maxConcurrentConnections = 0;
    $maxConcurrentConnectionsTime = 0;
    $concurrentConnections = 0;
    foreach ($timeConnection as $unixTime => $eventArray) {
        foreach ($eventArray as $event) {
            if ('C' === $event) {
                ++$concurrentConnections;
                if ($concurrentConnections > $maxConcurrentConnections) {
                    $maxConcurrentConnections = $concurrentConnections;
                    $maxConcurrentConnectionsTime = $unixTime;
                }
            } else {
                --$concurrentConnections;
            }
        }
    }

    $totalTraffic = 0;
    // convert the user list in unique user count for that day, rework array
    // key and determine total amount of traffic
    foreach ($statsData as $date => $entry) {
        $statsData[$date]['date'] = $date;
        $statsData[$date]['unique_user_count'] = count($entry['user_list']);
        unset($statsData[$date]['user_list']);
        $totalTraffic += $entry['traffic'];
    }

    FileIO::writeJsonFile(
        $outFile,
        [
            'days' => array_values($statsData),
            'total_traffic' => $totalTraffic,
            'generated_at' => time(),
            'max_concurrent_connections' => $maxConcurrentConnections,
            'max_concurrent_connections_time' => $maxConcurrentConnectionsTime,
            'first_entry' => $firstEntryTime,
            'last_entry' => $lastEntryTime,
            'unique_users' => count($uniqueUsers),
        ]
    );
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
