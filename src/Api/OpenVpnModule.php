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
use LetsConnect\Server\OpenVpn\ServerManager;
use LetsConnect\Server\Storage;

class OpenVpnModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Server\OpenVpn\ServerManager */
    private $serverManager;

    /** @var \LetsConnect\Server\Storage */
    private $storage;

    public function __construct(ServerManager $serverManager, Storage $storage)
    {
        $this->serverManager = $serverManager;
        $this->storage = $storage;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/client_connections',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                if (null !== $userId = $request->getQueryParameter('user_id', false)) {
                    $userId = InputValidation::userId($userId);
                }
                if (null !== $clientId = $request->getQueryParameter('client_id', false)) {
                    $clientId = InputValidation::clientId($clientId);
                }

                $clientConnections = $this->serverManager->connections();
                // add user information to connection information
                foreach ($clientConnections as $k => $v) {
                    foreach ($v['connections'] as $k1 => $v2) {
                        if (false === $certInfo = $this->storage->getUserCertificateInfo($v2['common_name'])) {
                            error_log(sprintf('"common_name "%s" not found', $v2['common_name']));
                            unset($clientConnections[$k]['connections'][$k1]);
                            continue;
                        }
                        if (null !== $userId) {
                            // filter by userId
                            if ($userId !== $certInfo['user_id']) {
                                unset($clientConnections[$k]['connections'][$k1]);
                                continue;
                            }
                        }
                        if (null !== $clientId) {
                            // filter by clientId
                            if ($clientId !== $certInfo['client_id']) {
                                unset($clientConnections[$k]['connections'][$k1]);
                                continue;
                            }
                        }

                        $clientConnections[$k]['connections'][$k1] = array_merge($v2, $certInfo);
                    }
                }

                return new ApiResponse('client_connections', $clientConnections);
            }
        );

        $service->post(
            '/kill_client',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $commonName = InputValidation::commonName($request->getPostParameter('common_name'));

                return new ApiResponse('kill_client', $this->serverManager->kill($commonName));
            }
        );
    }
}
