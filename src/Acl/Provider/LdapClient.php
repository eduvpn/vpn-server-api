<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use SURFnet\VPN\Server\Acl\Provider\Exception\LdapClientException;

class LdapClient
{
    /** @var resource */
    private $ldapResource;

    /**
     * @param string $ldapUri
     */
    public function __construct($ldapUri)
    {
        $this->ldapResource = @ldap_connect($ldapUri);
        if (false === $this->ldapResource) {
            // only with very old OpenLDAP will it ever return false...
            throw new LdapClientException(sprintf('unacceptable LDAP URI "%s"', $ldapUri));
        }
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
            throw new LdapClientException(
                sprintf(
                    'LDAP error: (%d) %s',
                    ldap_errno($this->ldapResource),
                    ldap_error($this->ldapResource)
                )
            );
        }
    }

    /**
     * @param string $baseDn
     * @param string $searchFilter
     * @param array  $attributeList
     *
     * @return array|false
     */
    public function search($baseDn, $searchFilter, array $attributeList = [])
    {
        $searchResource = @ldap_search(
            $this->ldapResource,    // link_identifier
            $baseDn,                // base_dn
            $searchFilter,          // filter
            $attributeList,         // attributes (dn is always returned...)
            0,                      // attrsonly
            0,                      // sizelimit
            10                      // timelimit
        );
        if (false === $searchResource) {
            throw new LdapClientException(
                sprintf(
                    'LDAP error: (%d) %s',
                    ldap_errno($this->ldapResource),
                    ldap_error($this->ldapResource)
                )
            );
        }

        $ldapEntries = @ldap_get_entries($this->ldapResource, $searchResource);
        if (false === $ldapEntries) {
            throw new LdapClientException(
                sprintf(
                    'LDAP error: (%d) %s',
                    ldap_errno($this->ldapResource),
                    ldap_error($this->ldapResource)
                )
            );
        }

        return $ldapEntries;
    }
}
