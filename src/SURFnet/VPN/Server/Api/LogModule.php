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

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use DateTime;
use fkooman\Json\Json;

class LogModule implements ServiceModuleInterface
{
    /** @var string */
    private $logPath;

    /** @var \DateTime */
    private $dateTime;

    public function __construct($logPath, DateTime $dateTime = null)
    {
        $this->logPath = $logPath;
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }
        $this->dateTime = $dateTime;
    }

    public function init(Service $service)
    {
        $service->get(
            '/log',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);

                $dateTime = $request->getUrl()->getQueryParameter('date_time');
                InputValidation::dateTime($dateTime);
                $dateTimeUnix = strtotime($dateTime);

                $ipAddress = $request->getUrl()->getQueryParameter('ip_address');
                InputValidation::ipAddress($ipAddress);

                return new ApiResponse('log', $this->get($dateTimeUnix, $ipAddress));
            }
        );

        $service->get(
            '/stats',
            function (Request $request, TokenInfo $tokenInfo) {
                $tokenInfo->getScope()->requireScope(['admin']);

                return new ApiResponse('stats', Json::decodeFile(sprintf('%s/stats.json', $this->logPath)));
            }
        );
    }

    public function get($dateTimeUnix, $ipAddress)
    {
        $returnData = [];

        $logData = Json::decodeFile(sprintf('%s/log.json', $this->logPath));
        foreach ($logData['entries'] as $k => $v) {
            $connectTime = $v['connect_time'];
            $disconnectTime = array_key_exists('disconnect_time', $v) ? $v['disconnect_time'] : null;

            if ($connectTime <= $dateTimeUnix && (is_null($disconnectTime) || $disconnectTime >= $dateTimeUnix)) {
                // XXX edge cases? still connected? just disconnected?
                $v4 = $v['v4'];
                $v6 = $v['v6'];
                if ($v4 === $ipAddress || $v6 === $ipAddress) {
                    $returnData[] = [
                        // XXX deal with still connected
                        'user_id' => $v['user_id'],
                        'v4' => $v4,
                        'v6' => $v6,
                        'config_name' => $v['config_name'],
                        'connect_time' => $connectTime,
                        'disconnect_time' => $disconnectTime,
                    ];
                }
            }
        }
        // XXX could there actually be multiple results?

        return $returnData;
    }
}
