<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl;

interface ProviderInterface
{
    /**
     * Get the groups a user is a member of.
     *
     * @param string $userId the userID of the user to request the groups of
     *
     * @return array the groups as an array containing the keys "id" and
     *               "displayName", empty array if no groups are available for this user
     */
    public function getGroups($userId);
}
