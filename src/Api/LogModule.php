<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Server\Api;

use LetsConnect\Common\Http\ApiResponse;
use LetsConnect\Common\Http\AuthUtils;
use LetsConnect\Common\Http\InputValidation;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;
use LetsConnect\Server\Storage;

class LogModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Server\Storage */
    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/log',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $dateTime = InputValidation::dateTime($request->getQueryParameter('date_time'));
                $ipAddress = InputValidation::ipAddress($request->getQueryParameter('ip_address'));

                $logData = $this->storage->getLogEntry($dateTime, $ipAddress);

                return new ApiResponse('log', $logData);
            }
        );
    }
}
