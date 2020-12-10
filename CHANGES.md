# Changelog

## 2.2.10 (...)
- do not rewrite `config.php` files during deployment, it is a terrible idea as
  it reformats the configuration file and removes the comments. To that end:
  - remove `bin/update-ip.php` and keep using the default IPs
  - remove `bin/update-api-secrets.php` and string replace them in depoy script
  - we *do* have `bin/suggest-ip.php` if you want to randomly generate nice 
    private IPv4 and IPv6 ranges and transplant them in your configuration file
    manually
- remove `tlsProtection` option support, `tls-crypt` will always be used

## 2.2.9 (2020-11-27)
- update for `ProfileConfig` refactor

## 2.2.8 (2020-10-20)
- implement changes for updated `Config` API
- fix `vpn-server-api-suggest-ip` on CentOS 7 (random_compat)

## 2.2.7 (2020-09-08)
- implement (experimental) support for `ECDSA` and `EdDSA` key types

## 2.2.6 (2020-08-31)
- restore default CA CN to be `VPN CA` instead of `Root CA`

## 2.2.5 (2020-08-31)
- make it possible to disable TLS connections to remote VPN daemons
- also expose `client_id` when getting the connection log
- fix type errors in `bin/status.php`
- add `bin/suggest-ip.php` script to print an IPv4 and IPv6 range as a 
  suggestion to use for your VPN profile configurations
- OpenVPN server certs now get the `hostName` configuration option from the 
  profile for their `CN` and `sAN`
- update for new vpn-ca 3.0.0 CLI API
- remove `bin/random-ip.php`, was never used

## 2.2.4 (2020-06-29)
- better connect/disconnect logging
- expose connection history of a user through API

## 2.2.3 (2020-05-21)
- expose `/app_usage` internal API
- better error message when trying to issue certificates that expire in the 
  past

## 2.2.2 (2020-05-12)
- expose CA information through API
- rework stats generation not to require a lot of memory on high-use systems
  (issue #73)

## 2.2.1 (2020-05-06)
- do not check `session_expires_at` for guest users that do not login locally

## 2.2.0 (2020-05-03)
- better logging in case permissions for connecting to VPN are not available
- log user login with updated permissions/expiry times
- enforce `user_update_session_info` to be a time in the future (and log it)
- consult `session_expires_at` in database before allowing VPN connection 
  (and log it)
- switch to [vpn-ca](https://github.com/letsconnectvpn/vpn-ca) for issuing
  certificates, drop [easy-rsa](https://github.com/OpenVPN/easy-rsa)
- remove issued certificates/keys from disk as they are not needed after 
  initial issuance

## 2.1.6 (2020-04-16)
- expose `port_client_count` which shows you the number of connections per 
  OpenVPN process through `vpn-server-api-status --json`

## 2.1.5 (2020-03-29)
- `status` tool now supports JSON output format using `--json`
- `status` tool now allows for specifying the `--alert` percentage as a 
  parameter
- `status` tool now has the option to also list connections using the 
  `--connections` flag. Only with `--json` and 
  [vpn-daemon](https://github.com/eduvpn/documentation/blob/v2/ADD_DAEMON_NODE.md)
- `status` tool has `--help` flag now

## 2.1.4 (2020-03-16)
- update for new [vpn-ca](https://git.tuxed.net/LC/vpn-ca/about/)
- better error capturing when running `vpn-ca` command from `VpnCa` class

## 2.1.3 (2020-03-12)
- rework `status` tool to list number of connected clients and how close to 
  capacity (available IPv4 addresses) to server is

## 2.1.2 (2020-01-20)
- update for new `LC/common`

## 2.1.1 (2019-12-10)
- support per profile tls-crypt keys for new deployments. Existing setups will
  keep one key for all profiles

## 2.1.0 (2019-11-21)
- update for new vpn-ca, client and server certificates are no longer written
  in separate directories
- implement VPN Daemon support
- update client connection API for portal

## 2.0.4 (2019-10-14)
- mention `tlsOneThree` as a configuration option in CONFIG_CHANGES to support 
  requiring client to use TLSv1.3
- updates for [vpn-ca](https://github.com/fkooman/vpn-ca) changes

## 2.0.3 (2019-08-29)
- no longer require OpenVPN to generate `tls-crypt` key
- add experimental [vpn-ca](https://github.com/fkooman/vpn-ca) support

## 2.0.2 (2019-08-13)
- update to `fkooman/otp-verifier` `^0.3`
- support `dnsSuffix` configuration option
- add [CONFIG_CHANGES](CONFIG_CHANGES.md) to indicate the changed configuration
  options since 2.0.0

## 2.0.1 (2019-06-07)
- fix unit tests

## 2.0.0 (2019-04-01)
- remove YubiKey support
- remove VOOT support
- remove compression framing support 
- remove tls-auth support
- remove "multi instance" support
- rename "entitlement" to "permission"
- add script to disconnect clients with expired certificates
- store all dates in `DateTime::ATOM` format
- store user's session expiry in database
- rework firewall configuration

## 1.4.9 (2018-12-05)
- fix bug where disabling a user would only disconnect the user from the first
  profile
- remove PHP error suppression

## 1.4.8 (2018-11-26)
- make sure user exists before checking for "entitlements"

## 1.4.7 (2018-11-22)
- create API call for `user_last_authenticated_at`
- create API call for `get_voot_token`
- also mention 16 ports for OpenVPN processes are supported
- `/add_client_certificate` requires the `expires_at` parameter now indicating
  when the certificate will expire exactly
- add ACL module for "entitlements", enabled by default
- no longer support "display_name" for groups
- deprecate all "backend" ACL / group information retrieval methods

## 1.4.6 (2018-11-09)
- show examples for routes config
- remove `blockSmb` from config template (it still works if it is set)
- extend API to allow restricting the validity (valid to) of issued client
  certificates
- add `blockLan` to config template

## 1.4.5 (2018-10-10)
- also cache the entitlements of a particular user together with the last
  time the user authenticated
- by default take nameservers from `/etc/resolv.conf` when using the 
  `bin/update-ip.php` script (on new deploys)
- update configuration template
- use `Json` helper class introduced in vpn-lib-common
- generate a `/25` by default as we only have 2 OpenVPN processes by default
- no longer avoid using `.42` as second octet in generated IP address

## 1.4.4 (2018-09-10)
- update for new vpn-lib-common API
- cleanup autoloader so Psalm will be able to verify the scripts in web and bin
  folder
- bind issued certificates/keys to OAuth client ID when requested through API
- use foreign key on `otp` and `otp_log` tables that removes OTP information
  when the user is deleted (issue #71)
- add filtering for `/client_connections` by `user_id` and `client_id`

## 1.4.3 (2018-08-05)
- many `vimeo/psalm` fixes
- add `psr/log` dependency

## 1.4.2 (2018-07-26)
- use `fkooman/sqlite-migrate`
- small docblock updates
- various small code fixes

## 1.4.1 (2018-07-23)
- fix OTP validation at VPN connect time

## 1.4.0 (2018-07-23)
- switch from `christian-riesen/otp` to `fkooman/otp-verifier`
- certificates are now sorted in reverse order (from newer to older)

## 1.3.0 (2018-07-02)
- deal with "lost" clients, that disconnected, but didn't get added to the 
  connection log (issue #70)
- `update-ip` script now assigns a /64 instead of /60 to IPv6 profile address
- introduce `last_authenticated_at` to keep track of when the user last 
  authenticated at the portal
- remove the ability to disable certificates, only users can be disabled now

## 1.2.14 (2018-06-13)
- fix unit test with bug fix release of `LC/openvpn-connection-manager`
- update dependencies

## 1.2.13 (2018-06-06)
- fix `status` CLI tool

## 1.2.12 (2018-06-06)
- use `LC/openvpn-connection-manager`
- introduce `tlsProtection` as replacement for `tlsCrypt` to make it possible
  to select `tls-auth`, `tls-crypt` (default) or `false` (no `tls-crypt`, no 
  `tls-auth`)

## 1.2.11 (2018-05-22)
- enable logging in OAuth client

## 1.2.10 (2018-05-03)
- expose the `valid_from` and `valid_to` values of certificates when providing
  certificate information

## 1.2.9 (2018-04-17)
- update default config to use `enableNat4` and `enableNat6` instead of 
  `useNat` to allow separate configuration of NAT for IPv4 and IPv6

## 1.2.8 (2018-04-12)
- update for `fkooman/oauth2-client` version 7
- add `1.1.1.1` as example in configuration file

## 1.2.7 (2018-04-05)
- fix test with updated `eduvpn/common`

## 1.2.6 (2018-03-15)
- allow different certificate expiry days for client and server certificates, 
  they will also immediately be used, not requiring a new "init" of the CA. 
  By default server cert expiry will be 365 days, client cert will be 180 days
  (issue #66)

## 1.2.5 (2018-02-26)
- introduce `exposedVpnProtoPorts` to allow listing different protocols/ports
  from what the OpenVPN processes listen on, e.g. for `tcp/443` port sharing

## 1.2.4 (2018-02-25)
- change default expiry of (server/client) certificates to 180 days
- enable `tlsCrypt` dropping 2.3 client support for new deploys, will keep 
  working for existing deploys

## 1.2.3 (2018-01-17)
- make quad9 the default DNS for new deploys
- split out statistics per profile instead of "global"

## 1.2.2 (2017-12-14)
- update `eduvpn/common`
- fix test for new `christian-riesen/otp`

## 1.2.1 (2017-12-13)
- cleanup autoloading
- update `eduvpn/common`
- update embedded easy-rsa
- make YubiKey validating more robust and simplify code

## 1.2.0 (2017-11-28)
- switch to `cn` attribute to retrieve name of group(s) instead of 
  `description`
- update `fkooman/oauth-client` to 
  [6.0.0](https://github.com/fkooman/php-oauth2-client/blob/master/CHANGES.md#600-2017-11-27)
- update LDAP configuration example
- support Active Directory for retrieving group membership from LDAP

## 1.1.1 (2017-11-24)
- make it possible to configure binding to LDAP before retrieving group
  membership

## 1.1.0 (2017-11-23)
- sort profile config for "Info" page in admin portal
- LDAP ACL Provider

## 1.0.7 (2017-11-20)
- support disabling compression, disable by default for new deploys
  - *NOTE* changing this with client configurations in the field WILL break 
    them!

## 1.0.6 (2017-10-30)
- remove `--reject4` and `--reject6` arguments from `update-ip` script
- refactor code to ease RPM/DEB packaging

## 1.0.5 (2017-10-23)
- handle VOOT error more gracefully now
- remove `fkooman/secookie` requirement
- update unit test for new `eduvpn/common`

## 1.0.4 (2017-10-04)
- fix security issue with 2FA where any YubiKey OTP would be accepted to 
  connect to VPN service when user was not enrolled for YubiKey 2FA
- update default range6 config option to be a /64

## 1.0.3 (2017-09-19)
- increase TOTP attempt count to 60 from 10 per hour (issue #64)
- fix source formatting and method annotations

## 1.0.2 (2017-07-23)
- another attempt at fixing #62

## 1.0.1 (2017-07-21)
- fix parsing connections at OpenVPN processes when clients are slow to connect
  (#62)

## 1.0.0 (2017-07-13)
- initial release
