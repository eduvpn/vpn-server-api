<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Api;

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\ProfileConfig;

class InfoModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function init(Service $service)
    {
        /* INFO */
        $service->get(
            '/instance_number',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                return new ApiResponse('instance_number', $this->config->getItem('instanceNumber'));
            }
        );

        $service->get(
            '/profile_list',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal', 'vpn-server-node']);

                $profileList = [];
                foreach ($this->config->getSection('vpnProfiles')->toArray() as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    $profileList[$profileId] = $profileConfig->toArray();
                }

                return new ApiResponse('profile_list', $profileList);
            }
        );
    }
}
