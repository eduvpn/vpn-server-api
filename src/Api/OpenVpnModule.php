<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Api;

use LC\Common\Http\ApiResponse;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\InputValidation;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Server\OpenVpn\ServerManager;
use LC\Server\Storage;

/**
 * Use direct OpenVPN management port socket connections.
 */
class OpenVpnModule implements ServiceModuleInterface
{
    /** @var \LC\Server\OpenVpn\ServerManager */
    private $serverManager;

    /** @var \LC\Server\Storage */
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
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                if (null !== $userId = $request->optionalQueryParameter('user_id')) {
                    $userId = InputValidation::userId($userId);
                }
                if (null !== $clientId = $request->optionalQueryParameter('client_id')) {
                    $clientId = InputValidation::clientId($clientId);
                }

                $connectionList = [];
                $clientConnections = $this->serverManager->connections();
                foreach ($clientConnections as $v) {
                    $profileId = $v['id'];
                    $connectionList[$profileId] = [];
                    foreach ($v['connections'] as $connectionInfo) {
                        if (false === $certInfo = $this->storage->getUserCertificateInfo($connectionInfo['common_name'])) {
                            error_log(sprintf('"common_name "%s" not found', $connectionInfo['common_name']));
                            continue;
                        }
                        if (null !== $userId) {
                            // filter by userId
                            if ($userId !== $certInfo['user_id']) {
                                continue;
                            }
                        }
                        if (null !== $clientId) {
                            // filter by clientId
                            if ($clientId !== $certInfo['client_id']) {
                                continue;
                            }
                        }

                        $connectionList[$profileId][] = array_merge($connectionInfo, $certInfo);
                    }
                }

                return new ApiResponse('client_connections', $connectionList);
            }
        );

        $service->post(
            '/kill_client',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $commonName = InputValidation::commonName($request->requirePostParameter('common_name'));
                $this->serverManager->kill($commonName);

                return new ApiResponse('kill_client');
            }
        );
    }
}
