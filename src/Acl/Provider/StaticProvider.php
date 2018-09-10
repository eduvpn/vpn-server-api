<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
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
     * @param string $userId
     *
     * @return array
     */
    public function getGroups($userId)
    {
        $memberOf = [];

        $groupIdList = array_keys($this->config->toArray());
        foreach ($groupIdList as $groupId) {
            $memberList = $this->config->getSection($groupId)->getSection('members')->toArray();
            if (!\in_array($userId, $memberList, true)) {
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
