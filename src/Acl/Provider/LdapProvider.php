<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use SURFnet\VPN\Server\Acl\Provider\Exception\LdapProviderException;
use SURFnet\VPN\Server\Acl\ProviderInterface;

class LdapProvider implements ProviderInterface
{
    /** @var resource */
    private $ldapResource;

    /** @var string */
    private $groupDn;

    /** @var string */
    private $filterTemplate;

    /**
     * @param string $ldapUri
     * @param string $groupDn
     * @param string $filterTemplate
     */
    public function __construct($ldapUri, $groupDn, $filterTemplate)
    {
        $this->ldapResource = @ldap_connect($ldapUri);
        if (false === $this->ldapResource) {
            // only with very old OpenLDAP will it ever return false...
            throw new LdapProviderException(sprintf('unacceptable LDAP URI "%s"', $ldapUri));
        }
        $this->groupDn = $groupDn;
        $this->filterTemplate = $filterTemplate;
    }

    /**
     * @param string|null $bindUser
     * @param string|null $bindPass
     *
     * @return void
     */
    public function bind($bindUser = null, $bindPass = null)
    {
        if (false === @ldap_bind($this->ldapResource, $bindUser, $bindPass)) {
            throw new LdapProviderException('unable to bind to LDAP server');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getGroups($userId)
    {
        // XXX we probably do NOT want to throw exceptions here...
        $searchFilter = str_replace('{{UID}}', $userId, $this->filterTemplate);
        $searchResource = @ldap_search(
            $this->ldapResource,    // link_identifier
            $this->groupDn,         // base_dn
            $searchFilter,          // filter
            ['description'],        // attributes (dn is always returned...)
            1,                      // attrsonly
            0,                      // sizelimit
            10                      // timelimit
        );
        if (false === $searchResource) {
            throw new LdapProviderException('unable to perform LDAP search');
        }

        $ldapEntries = @ldap_get_entries($this->ldapResource, $searchResource);
        if (false === $ldapEntries) {
            throw new LdapProviderException('unable to get LDAP entries');
        }

        $memberOf = [];
        for ($i = 0; $i < $ldapEntries['count']; ++$i) {
            $memberOf[] = [
                'id' => $ldapEntries[$i]['dn'],
                'displayName' => $ldapEntries[$i]['description'][0],
            ];
        }

        return $memberOf;
    }
}
