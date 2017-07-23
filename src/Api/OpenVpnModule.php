<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Api;

use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Server\OpenVpn\ServerManager;
use SURFnet\VPN\Server\Storage;

class OpenVpnModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\OpenVpn\ServerManager */
    private $serverManager;

    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    public function __construct(ServerManager $serverManager, Storage $storage)
    {
        $this->serverManager = $serverManager;
        $this->storage = $storage;
    }

    public function init(Service $service)
    {
        $service->get(
            '/client_connections',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $clientConnections = $this->serverManager->connections();
                // add user information to connection information
                foreach ($clientConnections as $k => $v) {
                    foreach ($v['connections'] as $k1 => $v2) {
                        if (false === $certInfo = $this->storage->getUserCertificateInfo($v2['common_name'])) {
                            error_log(sprintf('"common_name "%s" not found', $v2['common_name']));
                            unset($clientConnections[$k]['connections'][$k1]);
                            continue;
                        }
                        $clientConnections[$k]['connections'][$k1] = array_merge($v2, $certInfo);
                    }
                }

                return new ApiResponse('client_connections', $clientConnections);
            }
        );

        $service->post(
            '/kill_client',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal']);

                $commonName = InputValidation::commonName($request->getPostParameter('common_name'));

                return new ApiResponse('kill_client', $this->serverManager->kill($commonName));
            }
        );
    }
}
