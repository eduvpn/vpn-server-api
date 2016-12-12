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

use DateTime;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Server\Storage;

class SystemMessagesModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(Storage $storage, DateTime $dateTime)
    {
        $this->storage = $storage;
        $this->dateTime = $dateTime;
    }

    public function init(Service $service)
    {
        $service->get(
            '/system_messages',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal']);

                $type = InputValidation::messageType($request->getQueryParameter('message_type'));

                return new ApiResponse('system_messages', $this->storage->systemMessages($type));
            }
        );

        $service->post(
            '/add_system_message',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $type = InputValidation::messageType($request->getPostParameter('message_type'));

                // we do NOT sanitize or verify message as *everything* is
                // allowed! It will never be used as-is for showing in the
                // browser, as the user portal will escape it before showing
                // and the apps MUST interprete it as "text/plain".
                $message = $request->getPostParameter('message_body');

                return new ApiResponse('add_system_message', $this->storage->addSystemMessage($type, $message, $this->dateTime));
            }
        );

        $service->post(
            '/delete_system_message',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $messageId = InputValidation::messageId($request->getPostParameter('message_id'));

                return new ApiResponse('delete_system_message', $this->storage->deleteSystemMessage($messageId));
            }
        );
    }
}
