<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Server\Acl\ProviderInterface;

class StaticProvider implements ProviderInterface
{
    /** @var \SURFnet\VPN\Common\Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get the groups a user is a member of.
     *
     * @param string userId the userID of the user to request the groups of
     *
     * @return array the groups as an array containing the keys "id" and
     *               "displayName", empty array if no groups are available for this user
     */
    public function getGroups($userId)
    {
        $memberOf = [];

        $groupIdList = array_keys($this->config->toArray());
        foreach ($groupIdList as $groupId) {
            $memberList = $this->config->getSection($groupId)->getSection('members')->toArray();
            if (!is_array($memberList) || !in_array($userId, $memberList)) {
                continue;
            }

            $memberOf[] = [
                'id' => $groupId,
                'displayName' => $this->config->getSection($groupId)->getItem('displayName'),
            ];
        }

        return $memberOf;
    }
}
