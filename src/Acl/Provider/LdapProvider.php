<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use SURFnet\VPN\Server\Acl\ProviderInterface;
use Symfony\Component\Ldap\LdapClient;

class LdapProvider implements ProviderInterface
{
    /** @var \Symfony\Component\Ldap\LdapClient */
    private $ldap;

    /** @var string */
    private $groupDn;

    /** @var string */
    private $queryTemplate;

    /**
     * @param string $groupDn
     * @param string $queryTemplate
     */
    public function __construct(LdapClient $ldap, $groupDn, $queryTemplate)
    {
        $this->ldap = $ldap;
        $this->groupDn = $groupDn;
        $this->queryTemplate = $queryTemplate;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups($userId)
    {
        $q = str_replace('{{UID}}', $userId, $this->queryTemplate);
        $queryResults = $this->ldap->find($this->groupDn, $q, 'description');

        $memberOf = [];
        for ($i = 0; $i < $queryResults['count']; ++$i) {
            $memberOf[] = [
                'id' => $queryResults[$i]['dn'],
                'displayName' => $queryResults[$i]['description'][0],
            ];
        }

        error_log(var_export($memberOf, true));

        return $memberOf;
    }
}
