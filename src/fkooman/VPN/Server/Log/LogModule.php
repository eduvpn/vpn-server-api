<?php

namespace fkooman\VPN\Server\Log;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\Exception\BadRequestException;
use fkooman\IO\IO;
use fkooman\Http\JsonResponse;
use fkooman\VPN\Server\InputValidation;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use DateTime;

class LogModule implements ServiceModuleInterface
{
    /** @var ConnectionLog */
    private $connectionLog;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(ConnectionLog $connectionLog, DateTime $dateTime = null)
    {
        $this->connectionLog = $connectionLog;
        if(null === $dateTime) {
            $dateTime = new DateTime();
        }
        $this->dateTime = $dateTime;
    }

    public function init(Service $service)
    {
        $service->get(
            '/log/:showDate',
            function (Request $request, TokenInfo $tokenInfo, $showDate) {
                self::requireScope($tokenInfo, 'log_get');

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

    private static function requireScope(TokenInfo $tokenInfo, $requiredScope)
    {
        if (!$tokenInfo->getScope()->hasScope($requiredScope)) {
            throw new ForbiddenException('insufficient_scope', sprintf('"%s" scope required', $requiredScope));
        }
    }
}
