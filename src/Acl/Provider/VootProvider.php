<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use fkooman\OAuth\Client\Exception\TokenException;
use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use SURFnet\VPN\Server\Acl\ProviderInterface;

class VootProvider implements ProviderInterface
{
    /** @var \fkooman\OAuth\Client\OAuthClient */
    private $client;

    /** @var \fkooman\OAuth\Client\Provider */
    private $provider;

    /** @var string */
    private $vootUri;

    /**
     * @param string $vootUri
     */
    public function __construct(OAuthClient $client, Provider $provider, $vootUri)
    {
        $this->client = $client;
        $this->provider = $provider;
        $this->vootUri = $vootUri;
    }

    /**
     * @param string $userId
     *
     * @return array<string>
     */
    public function getGroups($userId)
    {
        // fetch the groups and extract the membership data
        return self::extractMembership(
            $this->fetchGroups($userId)
        );
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    private function fetchGroups($userId)
    {
        try {
            if (false === $response = $this->client->get($this->provider, $userId, 'groups', $this->vootUri)) {
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

    /**
     * @return array<string>
     */
    private static function extractMembership(array $responseData)
    {
        $memberOf = [];
        foreach ($responseData as $groupEntry) {
            if (!\is_array($groupEntry)) {
                continue;
            }
            if (!array_key_exists('id', $groupEntry)) {
                continue;
            }
            if (!\is_string($groupEntry['id'])) {
                continue;
            }
            $memberOf[] = $groupEntry['id'];
        }

        return $memberOf;
    }
}
