<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use Psr\Log\LoggerInterface;
use SURFnet\VPN\Common\Exception\LdapClientException;
use SURFnet\VPN\Common\LdapClient;
use SURFnet\VPN\Server\Acl\ProviderInterface;

class LdapProvider implements ProviderInterface
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \SURFnet\VPN\Common\LdapClient */
    private $ldapClient;

    /** @var string */
    private $groupDn;

    /** @var string */
    private $filterTemplate;

    /** @var string|null */
    private $bindDn;

    /** @var string|null */
    private $bindPass;

    /**
     * @param string      $groupDn
     * @param string      $filterTemplate
     * @param string|null $bindDn
     * @param string|null $bindPass
     */
    public function __construct(
        LoggerInterface $logger,
        LdapClient $ldapClient,
        $groupDn,
        $filterTemplate,
        $bindDn,
        $bindPass
    ) {
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
        $this->groupDn = $groupDn;
        $this->filterTemplate = $filterTemplate;
        $this->bindDn = $bindDn;
        $this->bindPass = $bindPass;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups($userId)
    {
        $searchFilter = str_replace('{{UID}}', LdapClient::escapeFilter($userId), $this->filterTemplate);
        try {
            $this->ldapClient->bind($this->bindDn, $this->bindPass);
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
            // group membership(s)
            $this->logger->error($e->getMessage());

            return [];
        }
    }
}
