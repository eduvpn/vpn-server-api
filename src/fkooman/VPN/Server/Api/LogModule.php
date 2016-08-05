<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\Server\Api;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\VPN\Server\InputValidation;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use DateTime;
use fkooman\VPN\Server\ApiResponse;
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
                    $commonName = explode(':', $k, 2)[0];
                    $userId = explode('_', $commonName, 2)[0];
                    $configName = explode('_', $commonName, 2)[1];

                    $returnData[] = [
                        // XXX deal with still connected
                        'user_id' => $userId,
                        'v4' => $v4,
                        'v6' => $v6,
                        'config_name' => $configName,
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
