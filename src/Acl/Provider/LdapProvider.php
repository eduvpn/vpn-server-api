<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
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
    private $groupBaseDn;

    /** @var string */
    private $memberFilterTemplate;

    /** @var string|null */
    private $userBaseDn;

    /** @var string|null */
    private $userIdFilterTemplate;

    /** @var string|null */
    private $bindDn;

    /** @var string|null */
    private $bindPass;

    /**
     * @param string      $groupBaseDn
     * @param string      $memberFilterTemplate
     * @param string|null $userBaseDn
     * @param string|null $userIdFilterTemplate
     * @param string|null $bindDn
     * @param string|null $bindPass
     */
    public function __construct(
        LoggerInterface $logger,
        LdapClient $ldapClient,
        $groupBaseDn,
        $memberFilterTemplate,
        $userBaseDn = null,
        $userIdFilterTemplate = null,
        $bindDn = null,
        $bindPass = null
    ) {
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
        $this->groupBaseDn = $groupBaseDn;
        $this->memberFilterTemplate = $memberFilterTemplate;
        $this->userBaseDn = $userBaseDn;
        $this->userIdFilterTemplate = $userIdFilterTemplate;
        $this->bindDn = $bindDn;
        $this->bindPass = $bindPass;
    }

    /**
     * Get the groups a user is a member of.
     *
     * @param string $userId the userID of the user to request the groups of
     *
     * @return array the groups as an array containing the keys "id" and
     *               "displayName", empty array if no groups are available for this user
     */
    public function getGroups($userId)
    {
        try {
            $this->ldapClient->bind($this->bindDn, $this->bindPass);

            $memberFilter = $this->getMemberFilter($userId);
            $ldapEntries = $this->ldapClient->search(
                $this->groupBaseDn,
                $memberFilter,
                ['cn']
            );

            $memberOf = [];
            for ($i = 0; $i < $ldapEntries['count']; ++$i) {
                $memberOf[] = [
                    'id' => $ldapEntries[$i]['dn'],
                    'displayName' => $ldapEntries[$i]['cn'][0],
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

    /**
     * @param string $userId
     *
     * @return string
     */
    private function getMemberFilter($userId)
    {
        if (null === $this->userBaseDn) {
            return str_replace('{{UID}}', LdapClient::escapeFilter($userId), $this->memberFilterTemplate);
        }

        // we first have to determine the user DN as we can't directly use
        // the userId in the memberFilter
        $userIdFilter = str_replace('{{UID}}', LdapClient::escapeFilter($userId), $this->userIdFilterTemplate);
        $ldapEntries = $this->ldapClient->search(
            $this->userBaseDn,
            $userIdFilter
        );
        if (1 !== $ldapEntries['count']) {
            // we did not find the user's DN, or too many
            return '';
        }
        $userDn = $ldapEntries[0]['dn'];

        return str_replace('{{DN}}', $userDn, $this->memberFilterTemplate);
    }
}
