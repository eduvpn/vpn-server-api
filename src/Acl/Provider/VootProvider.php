<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use fkooman\OAuth\Client\OAuthClient;
use SURFnet\VPN\Server\Acl\ProviderInterface;

class VootProvider implements ProviderInterface
{
    /** @var \fkooman\OAuth\Client\OAuthClient */
    private $client;

    /** @var string */
    private $vootUri;

    public function __construct(OAuthClient $client, $vootUri)
    {
        $this->client = $client;
        $this->vootUri = $vootUri;
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
        $this->client->setUserId($userId);

        // fetch the groups and extract the membership data
        return self::extractMembership(
            $this->fetchGroups()
        );
    }

    private function fetchGroups()
    {
        if (false === $response = $this->client->get('groups', $this->vootUri)) {
            return [];
            // XXX need to delete the token?
        }

        if (!$response->isOkay()) {
            // we should probably log some stuff here, but for now just assume
            // there are no groups for the user...
            return [];
        }

        return $response->json();
    }

    private static function extractMembership(array $responseData)
    {
        $memberOf = [];
        foreach ($responseData as $groupEntry) {
            if (!is_array($groupEntry)) {
                continue;
            }
            if (!array_key_exists('id', $groupEntry)) {
                continue;
            }
            if (!is_string($groupEntry['id'])) {
                continue;
            }
            $displayName = self::getDisplayName($groupEntry);

            $memberOf[] = [
                'id' => $groupEntry['id'],
                'displayName' => $displayName,
            ];
        }

        return $memberOf;
    }

    private static function getDisplayName(array $groupEntry)
    {
        if (!array_key_exists('displayName', $groupEntry)) {
            return $groupEntry['id'];
        }

        if (is_string($groupEntry['displayName'])) {
            return $groupEntry['displayName'];
        }

        if (is_array($groupEntry['displayName'])) {
            if (array_key_exists('en', $groupEntry['displayName'])) {
                return $groupEntry['displayName']['en'];
            }

            return array_values($groupEntry['displayName'])[0];
        }

        return $groupEntry['id'];
    }
}
