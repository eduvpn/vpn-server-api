<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LetsConnect\Server\Api;

use LetsConnect\Common\Config;
use LetsConnect\Common\Http\ApiResponse;
use LetsConnect\Common\Http\AuthUtils;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Http\ServiceModuleInterface;
use LetsConnect\Common\ProfileConfig;

class InfoModule implements ServiceModuleInterface
{
    /** @var \LetsConnect\Common\Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        /* INFO */
        $service->get(
            '/profile_list',
            /**
             * @return \LetsConnect\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-server-node']);

                $profileList = [];
                foreach ($this->config->getSection('vpnProfiles')->toArray() as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig($profileData);
                    $profileConfigArray = $profileConfig->toArray();
                    ksort($profileConfigArray);
                    $profileList[$profileId] = $profileConfigArray;
                }

                return new ApiResponse('profile_list', $profileList);
            }
        );
    }
}
