<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use fkooman\OAuth\Client\Exception\TokenException;
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
     * {@inheritdoc}
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
        try {
            if (false === $response = $this->client->get('groups', $this->vootUri)) {
                // we do not have an access_token, it expired, was revoked, was
                // not accepted by the VOOT endpoint or the refresh_token is no
                // longer valid (needs new authorization through browser!)
                //
                // XXX how to inform the user of this as this basically means
                // that users cannot connect to VPN (if group membership was
                // required) until they login to the portal again
                return [];
            }
        } catch (TokenException $e) {
            // unable to use refresh_token, unexpected error from server
            return [];
        }

        if (!$response->isOkay()) {
            // VOOT responses should be HTTP 200 responses... there is
            // something else wrong...
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
