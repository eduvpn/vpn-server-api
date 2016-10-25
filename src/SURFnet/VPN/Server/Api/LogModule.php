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
namespace SURFnet\VPN\Server\Api;

use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\FileIO;

class LogModule implements ServiceModuleInterface
{
    /** @var ConnectionLog */
    private $connectionLog;

    public function __construct(ConnectionLog $connectionLog)
    {
        $this->connectionLog = $connectionLog;
    }

    public function init(Service $service)
    {
        $service->get(
            '/log',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-admin-portal']);

                $dateTime = $request->getQueryParameter('date_time');
                InputValidation::dateTime($dateTime);

                // do not convert if we have a number, it is probably already unix time
                $dateTimeUnix = is_numeric($dateTime) ? intval($dateTime) : strtotime($dateTime);

                $ipAddress = $request->getQueryParameter('ip_address');
                InputValidation::ipAddress($ipAddress);

                $logData = $this->connectionLog->get($dateTimeUnix, $ipAddress);
                if (false !== $logData) {
                    foreach ($logData as $k => $value) {
                        $logData[$k]['user_id'] = substr($value['common_name'], 0, strpos($value['common_name'], '_'));
                        $logData[$k]['config_name'] = substr($value['common_name'], strpos($value['common_name'], '_') + 1);
                    }
                }

                return new ApiResponse('log', $logData);
            }
        );

//        $service->get(
//            '/stats',
//            function (Request $request, array $hookData) {
//                Utils::requireUser($hookData, ['vpn-admin-portal']);
//                $statsFile = sprintf('%s/stats.json', $this->dataDir);

//                return new ApiResponse('stats', FileIO::readJsonFile($statsFile));
//            }
//        );
    }
}
