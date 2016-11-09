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

use Psr\Log\LoggerInterface;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Request;

/**
 * Handle API calls for Groups.
 */
class GroupsModule implements ServiceModuleInterface
{
    /** @var array */
    private $groupProviders;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(array $groupProviders, LoggerInterface $logger)
    {
        $this->groupProviders = $groupProviders;
        $this->logger = $logger;
    }

    public function init(Service $service)
    {
        $service->get(
            '/user_groups',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-user-portal', 'vpn-server-node']);
                $userId = $request->getQueryParameter('user_id');
                InputValidation::userId($userId);

                $groupMembership = [];
                foreach ($this->groupProviders as $groupProvider) {
                    $groupMembership = array_merge($groupMembership, $groupProvider->getGroups($userId));
                }

                return new ApiResponse('user_groups', $groupMembership);
            }
        );
    }
}
