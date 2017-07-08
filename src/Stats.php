<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server;

use DateTime;

class Stats
{
    /** @var \DateTime */
    private $now;

    public function __construct(DateTime $now)
    {
        $this->now = $now;
    }

    public function get(array $logEntries)
    {
        $statsData = [];
        $timeConnection = [];
        $uniqueUsers = [];
        $activeUserCount = 0;

        foreach ($logEntries as $entry) {
            $userId = $entry['user_id'];
            $connectedAt = $entry['connected_at'];
            $disconnectedAt = $entry['disconnected_at'];
            $connectedAtDateTime = new DateTime($entry['connected_at']);
            $dateOfConnection = $connectedAtDateTime->format('Y-m-d');

            if (!array_key_exists($dateOfConnection, $statsData)) {
                $statsData[$dateOfConnection] = [
                    'number_of_connections' => 0,
                    'bytes_transferred' => 0,
                    'unique_user_list' => [],
                ];
            }

            if (is_null($disconnectedAt)) {
                $activeUserCount += 1;
            }

            $statsData[$dateOfConnection]['number_of_connections'] += 1;
            $statsData[$dateOfConnection]['bytes_transferred'] += $entry['bytes_transferred'];

            // add it to table to be able to determine max concurrent connection
            // count
            if (!array_key_exists($connectedAt, $timeConnection)) {
                $timeConnection[$connectedAt] = [];
            }
            $timeConnection[$connectedAt][] = 'C';

            if (!is_null($disconnectedAt)) {
                if (!array_key_exists($disconnectedAt, $timeConnection)) {
                    $timeConnection[$disconnectedAt] = [];
                }
                $timeConnection[$disconnectedAt][] = 'D';
            }

            // unique user list per day
            if (!in_array($userId, $statsData[$dateOfConnection]['unique_user_list'])) {
                $statsData[$dateOfConnection]['unique_user_list'][] = $userId;
            }

            // unique user list for the whole logging period
            if (!in_array($userId, $uniqueUsers)) {
                $uniqueUsers[] = $userId;
            }
        }

        ksort($timeConnection);
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
            $statsData[$date]['unique_user_count'] = count($entry['unique_user_list']);
            unset($statsData[$date]['unique_user_list']);
            $totalTraffic += $entry['bytes_transferred'];
        }

        return [
            'days' => array_values($statsData),
            'total_traffic' => $totalTraffic,
            'generated_at' => $this->now->getTimestamp(),
            'max_concurrent_connections' => $maxConcurrentConnections,
            'max_concurrent_connections_time' => $maxConcurrentConnectionsTime,
            'unique_user_count' => count($uniqueUsers),
            'active_user_count' => $activeUserCount,
        ];
    }
}
