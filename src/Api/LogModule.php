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

use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Server\Storage;

class LogModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function init(Service $service)
    {
        $service->get(
            '/log',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $dateTime = InputValidation::dateTime($request->getQueryParameter('date_time'));
                $ipAddress = InputValidation::ipAddress($request->getQueryParameter('ip_address'));

                $logData = $this->storage->getLogEntry($dateTime, $ipAddress);

                // XXX probably should be empty instead of false?!
                // we need to get the external_user_id instead and expose that... not the internal, also expose CN so it can be blocked by admin
                if (false !== $logData) {
                    foreach ($logData as $k => $value) {
                        $logData[$k]['user_id'] = substr($value['common_name'], 0, strpos($value['common_name'], '_'));
                        $logData[$k]['config_name'] = substr($value['common_name'], strpos($value['common_name'], '_') + 1);
                    }
                }

                return new ApiResponse('log', $logData);
            }
        );
    }
}
