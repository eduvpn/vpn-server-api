<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Api;

use DateInterval;
use DateTime;
use LC\Common\FileIO;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use RuntimeException;

class StatsModule implements ServiceModuleInterface
{
    /** @var string */
    private $dataDir;

    /** @var \DateTime */
    private $dateTime;

    /**
     * @param string $dataDir
     */
    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
        $this->dateTime = new DateTime();
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/stats',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);
                $statsFile = sprintf('%s/stats.json', $this->dataDir);

                try {
                    return new ApiResponse('stats', $this->prepareStats(FileIO::readJsonFile($statsFile)));
                } catch (RuntimeException $e) {
                    // no stats file available yet
                    return new ApiResponse('stats', false);
                }
            }
        );
    }

    /**
     * Make sure we return data from the last month only. We have very
     * complicated file format to expose, so simplify it for the API by using
     * the days as keys. This is something we should really simplify in the
     * future @ stats generation phase... What a mess!
     *
     * @param array $statsData
     *
     * @return array
     */
    private function prepareStats(array $statsData)
    {
        $dateList = [];
        $currentDate = date_sub(clone $this->dateTime, new DateInterval('P1M'));
        while ($currentDate < $this->dateTime) {
            $dateList[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }

        foreach ($statsData['profiles'] as $profileId => $profileStats) {
            $dayStatsList = $profileStats['days'];
            $filteredDays = [];
            foreach ($dateList as $dateStr) {
                $filteredDays[$dateStr] = [
                    'bytes_transferred' => 0,
                    'number_of_connections' => 0,
                    'unique_user_count' => 0,
                ];
            }
            foreach ($dayStatsList as $dayEntry) {
                if (\array_key_exists($dayEntry['date'], $filteredDays)) {
                    $filteredDays[$dayEntry['date']] = [
                        'bytes_transferred' => $dayEntry['bytes_transferred'],
                        'number_of_connections' => $dayEntry['number_of_connections'],
                        'unique_user_count' => $dayEntry['unique_user_count'],
                    ];
                }
            }

            $statsData['profiles'][$profileId]['days'] = $filteredDays;
        }

        return $statsData;
    }
}
