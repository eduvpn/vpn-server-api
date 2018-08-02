<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Api;

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
            '/system_messages',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal']);

                $type = InputValidation::messageType($request->getQueryParameter('message_type'));

                return new ApiResponse('system_messages', $this->storage->systemMessages($type));
            }
        );

        $service->post(
            '/add_system_message',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $type = InputValidation::messageType($request->getPostParameter('message_type'));

                // we do NOT sanitize or verify message as *everything* is
                // allowed! It will never be used as-is for showing in the
                // browser, as the user portal will escape it before showing
                // and the apps MUST interprete it as "text/plain".
                $message = $request->getPostParameter('message_body');

                $this->storage->addSystemMessage($type, $message);

                return new ApiResponse('add_system_message', true);
            }
        );

        $service->post(
            '/delete_system_message',
            /**
             * @return \SURFnet\VPN\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $messageId = InputValidation::messageId($request->getPostParameter('message_id'));

                $this->storage->deleteSystemMessage($messageId);

                return new ApiResponse('delete_system_message', true);
            }
        );
    }
}
