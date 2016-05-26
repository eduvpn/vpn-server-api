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
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\JsonResponse;
use fkooman\VPN\Server\InputValidation;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use DateTime;
use fkooman\VPN\Server\ConnectionLog;

class LogModule implements ServiceModuleInterface
{
    /** @var ConnectionLog */
    private $connectionLog;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(ConnectionLog $connectionLog, DateTime $dateTime = null)
    {
        $this->connectionLog = $connectionLog;
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }
        $this->dateTime = $dateTime;
    }

    public function init(Service $service)
    {
        $service->get(
            '/log/:showDate',
            function (Request $request, TokenInfo $tokenInfo, $showDate) {
                $tokenInfo->getScope()->requireScope(['admin']);

                InputValidation::date($showDate);

                $showDateUnix = strtotime($showDate);
                $minDate = strtotime('today -31 days', $this->dateTime->getTimeStamp());
                $maxDate = strtotime('tomorrow', $this->dateTime->getTimeStamp());

                if ($showDateUnix < $minDate || $showDateUnix >= $maxDate) {
                    throw new BadRequestException('invalid date range');
                }

                $showDateUnixMin = strtotime('today', $showDateUnix);
                $showDateUnixMax = strtotime('tomorrow', $showDateUnix);

                return self::getResponse('log', $this->connectionLog->getConnectionHistory($showDateUnixMin, $showDateUnixMax));
            }
        );
    }

    private static function getResponse($key, $responseData)
    {
        $response = new JsonResponse();
        $response->setBody(
            [
                'data' => [
                    $key => $responseData,
                ],
            ]
        );

        return $response;
    }
}
