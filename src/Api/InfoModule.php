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

use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Config;
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
            '/profile_info',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal', 'vpn-server-node']);

                $profileId = $request->getQueryParameter('profile_id');
                InputValidation::profileId($profileId);

                $profileConfig = new ProfileConfig($this->config->v('vpnProfiles', $profileId));

                return new ApiResponse('profile_info', $profileConfig->v());
            }
        );
    }
}
