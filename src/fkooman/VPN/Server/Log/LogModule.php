<?php

namespace fkooman\VPN\Server\Log;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\JsonResponse;
use fkooman\VPN\Server\InputValidation;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use DateTime;
use fkooman\VPN\Server\Utils;

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
                Utils::requireScope($tokenInfo, ['admin']);

                InputValidation::date($showDate);

                $showDateUnix = strtotime($showDate);
                $minDate = strtotime('today -31 days', $this->dateTime->getTimeStamp());
                $maxDate = strtotime('tomorrow', $this->dateTime->getTimeStamp());

                if ($showDateUnix < $minDate || $showDateUnix >= $maxDate) {
                    throw new BadRequestException('invalid date range');
                }

                $showDateUnixMin = strtotime('today', $showDateUnix);
                $showDateUnixMax = strtotime('tomorrow', $showDateUnix);

                $response = new JsonResponse();
                if (is_null($this->connectionLog)) {
                    $responseData = array(
                        'ok' => false,
                        'error' => 'unable to connect to log database',
                    );
                } else {
                    $responseData = array(
                        'ok' => true,
                        'history' => $this->connectionLog->getConnectionHistory($showDateUnixMin, $showDateUnixMax),
                    );
                }
                $response->setBody($responseData);

                return $response;
            }
        );
    }
}
