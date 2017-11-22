<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use SURFnet\VPN\Server\Acl\Provider\Exception\LdapClientException;
use SURFnet\VPN\Server\Acl\ProviderInterface;

class LdapProvider implements ProviderInterface
{
    /** @var LdapClient */
    private $ldapClient;

    /** @var string */
    private $groupDn;

    /** @var string */
    private $filterTemplate;

    /**
     * @param LdapClient $ldapClient
     * @param string     $groupDn
     * @param string     $filterTemplate
     */
    public function __construct(LdapClient $ldapClient, $groupDn, $filterTemplate)
    {
        $this->ldapClient = $ldapClient;
        $this->groupDn = $groupDn;
        $this->filterTemplate = $filterTemplate;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups($userId)
    {
        $searchFilter = str_replace('{{UID}}', $userId, $this->filterTemplate);
        try {
            $ldapEntries = $this->ldapClient->search(
                $this->groupDn,
                $searchFilter,
                ['description']
            );

            $memberOf = [];
            for ($i = 0; $i < $ldapEntries['count']; ++$i) {
                $memberOf[] = [
                    'id' => $ldapEntries[$i]['dn'],
                    'displayName' => $ldapEntries[$i]['description'][0],
                ];
            }

            return $memberOf;
        } catch (LdapClientException $e) {
            error_log($e->getMessage());
            var_dump($e->getMessage());

            return [];
        }
    }
}
