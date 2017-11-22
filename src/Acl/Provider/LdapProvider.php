<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use Psr\Log\LoggerInterface;
use SURFnet\VPN\Server\Acl\Provider\Exception\LdapClientException;
use SURFnet\VPN\Server\Acl\ProviderInterface;

class LdapProvider implements ProviderInterface
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var LdapClient */
    private $ldapClient;

    /** @var string */
    private $groupDn;

    /** @var string */
    private $filterTemplate;

    /**
     * @param string $groupDn
     * @param string $filterTemplate
     */
    public function __construct(LoggerInterface $logger, LdapClient $ldapClient, $groupDn, $filterTemplate)
    {
        $this->logger = $logger;
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
            // an error occurred, log it, and for now assume user has no
            // group membership
            $this->logger->error($e->getMessage());

            return [];
        }
    }
}
