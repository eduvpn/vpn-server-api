# Changelog

## 8.2.0 (2016-07-14)
- redo group support to have identifiers and display names instead of just 
  identifiers and create mapping in the pool configuration
- remove RemoteAcl for now

## 8.1.0 (2016-07-09)
- remove the CRL support, only support disabling configurations

## 8.0.5 (2016-06-07)
- add user ID to info log when user is not a member of the group
- create the otp_secrets and voot_secrets directories with the correct 
  permissions if they don't exist yet

## 8.0.4 (2016-06-07)
- implement VootAcl backend

## 8.0.3 (2016-05-27)
- cleanup `RemoteAcl`, make it more useful

## 8.0.2 (2016-05-27)
- update `config/pools.yaml` a bit

## 8.0.1 (2016-05-26)
- fix `bin/init` and `bin/housekeeping`

## 8.0.0 (2016-05-26)
- major refactoring of the code
- implement group ACL support (static, remote)
- change how user/CN blocking works by just using files, simplifying things a 
  lot
- cleanup API responses, make them similar
- enforce `totp` as username now for 2FA/OTP setups

## 7.3.0 (2016-05-23)
- add ability to enable/disable log using the `enableLog` option in the pool 
  configuration
- use `tcp-nodelay` macro in generated server config for TCP servers instead
  of expanded macro
- fix network splitting with small networks

## 7.2.0 (2016-05-20)
- redo `server-config` to require the use of `--generate` to generate a new 
  cert and DH params

## 7.1.0 (2016-05-19)
- add `useNat` and `extIf` to the `pools.yaml` file
- allow turning on/off NAT per pool
- `extIf` can be different per pool, allowing more flexible setups with 
  multiple interfaces

## 7.0.2 (2016-05-19)
- prevent DNS leakage on Windows

## 7.0.1 (2016-05-18)
- again fix a proto case when an actual IPv4 address is specified in listen

## 7.0.0 (2016-05-18)
- rename `config/ip.yaml` to `config/pools.yaml` and update file format, see
  `config/pools.yaml.example`
- implement "Pools" allowing multiple groups of instances of OpenVPN servers 
  running on different IP addresses
- major refactoring and cleaning up of code
- use `/etc/openvpn/tls` to store the certificates and keys now instead of 
  inline, making the `server-config --reuse` a lot simpler to implement
- fix configuration file permissions when running `server-config`
- no longer specify the instances manually, all autodetect
- no longer specify the firewall ports manually, all autodetect
- much more code coverage for unit tests

## 6.0.3 (2016-05-11)
- expose client-to-client config setting through info API

## 6.0.2 (2016-05-11)
- support client-to-client connectivity (optional, default disabled)

## 6.0.1 (2016-05-10)
- fix iOS sniproxy/openvpn IPv6 snafu

## 6.0.0 (2016-05-06)
- update configuration format of `ip.yaml`
- support restricting destination routes, i.e. not only default gateway 
  configurations

## 5.0.2 (2016-04-27)
- allow OpenVPN to read the user/CN config files by default when the directories
  are created

## 5.0.1 (2016-04-27)
- update `fkooman/io` to allow creating directory when writing files

## 5.0.0 (2016-04-27)
- refactor storing common name and user configuration in the backend
- support storing 2FA secret in user configuration
- script to verify OTP token
- allow `server-config` to verify user password as OTP token
- update the API, allowing setting both user specific settings as well as CN 
  specific settings

## 4.0.6 (2016-04-20)
- automatically determine `max-clients` based on the IP range when generating 
  server configurations

## 4.0.5 (2016-04-20)
- update the default config file to listen on additional UDP ports

## 4.0.4 (2016-04-19)
- tag new release, something got confused

## 4.0.3 (2016-04-19)
- allow specifying the `listen` directive in `config.yaml` to bind instances
  to particular IP addresses
- default to listening on `::` with either `udp6` or `tcp6-server` as proto
- fix small issue if no firewall was specified in `ip.yaml` that only `tcp/1194` 
  was opened instead of also `udp/1194`

## 4.0.2 (2016-04-13)
- move the default management ports of OpenVPN to the 1194x port range

## 4.0.1 (2016-04-13)
- allow rejecting IPv6 traffic routing, forcing IPv4 only
- update `ip.yaml` template

## 4.0.0 (2016-04-13)
- for now remove all custom route support, go back to simple split IP range 
  over the UDP/TCP instances
- simplify firewall
- implement `--reuse` option to `server-config` script to use the certificates 
  and keys in the existing server configs
- slightly change the `v6` -> `prefix` config option in `ip.yaml` to also 
  require the net size, IPv6 blocks of size `/64` will be taken from this 
  prefix
- fix iOS with "Force AES-CBC ciphers" configuration option

## 3.4.4 (2016-04-08)
- do not send the 0.0.0.0/0 default route any more, this may break
  file sharing on Windows, but we do not really support that now anyway

## 3.4.3 (2016-04-01)
- improve generate-firewall script to also include help text and
  show error when required parameters are missing
- allow specifying allowed input ports in `ip.yaml` instead of only the 
  default set

## 3.4.2 (2016-03-25)
- update `fkooman/json`

## 3.4.1 (2016-03-24)
- remove `fkooman/io` dependency by directly using DateTime object instead
  of beating around the bush

## 3.4.0 (2016-03-15)
- remove dependency on Twig for generating server configuration file
- configure the OpenVPN instances now more detailed in the config file 
  so the OpenVPN config files can be generated directly for all instances
  as well as installed at the same time

## 3.3.0 (2016-03-14)
- only push specific destination network routes instead of always the default 
  gateway
- only push DNS servers in 'default gateway' situation
- refactor code to reduce size of `client-connect` script and improve testing
- allow configuration of DNS addresses in `ip.yaml`
- remove 'default gateways' and DNS push from the `server.twig` template
- enable `client-connect` by default

## 3.2.0 (2016-03-07)
- script to generate firewall based on IP config file
- cleanup the `info/net` API call
- fix for allowing the use of the full IP range for client IP 
  assignments, e.g. if you only have a small public IPv4 space 
  and want only 1 'pool'
- make it possible to install the firewall in the system

## 3.1.1 (2016-03-04)
- fix logging, IPv6 address is now normalized first

## 3.1.0 (2016-03-04)
- generate DH locally in server-config script

## 3.0.0 (2016-03-03)
- major refactor and update
- support multiple IP pools and assignments to CNs
- fix CRL fetching
- support multiple API consumers with different permissions
- update unit tests

## 2.5.3 (2016-02-29)
- flush routes after modifying route table

## 2.5.2 (2016-02-25)
- use external ArrayBearerValidator

## 2.5.1 (2016-02-25)
- add `housekeeping` script to remove older log entries

## 2.5.0 (2016-02-24)
- switch to Bearer authentication from Basic Authentication to improve
  performance (*BREAKING CONFIG*)

## 2.4.2 (2016-02-22)
- redo input validation, fix some small bugs and only do input validation 
  in the modules

## 2.4.1 (2016-02-22)
- restore logging
- add some missing dependencies to `composer.json`

## 2.4.0 (2016-02-22)
- remove `SimpleError` class
- do not use Monolog for connect and disconnect scripts
- determine `disconnect_time_unix` in the disconnect script
  and not in the database class
- major refactor of the code to make it better testable and
  add lots of tests
- restructure connect and disconnect scripts again to make
  them more robust

## 2.3.1 (2016-02-20)
- static IP can also not be network and broadcast of `poolRange`
- do not allow to set static IP addresses already in use
- make IP ranges available through API for edit page

## 2.3.0 (2016-02-19)
- delete routes without specifying `dev`
- use `ipRange` and `poolRange` in `client.yaml` now (**BREAKING**)
- check if specified IP through API can be used for static
  assignment, i.e.: not part of the pool
 
## 2.2.1 (2016-02-18)
- more defensive connect and disconnect script

## 2.2.0 (2016-02-18)
- implement setting/getting static IP addresses through the API
- add/del route to correct tunnel on connect/disconnect
- refactor disable API
- use new JSON format to store static configuration instead of generating 
  OpenVPN config snippets (**BREAKING**)
- remove `log.yaml` config file, use `client.yaml` instead (**BREAKING**)

## 2.1.2 (2016-01-20)
- by default only show log of the current day, allow query parameter to 
  go back in time up to 31 days
- fix a bug where the wrong configuration file was used to retrieve the 
  log DSN

## 2.1.1 (2016-01-18)
- use separate configuration file for connection log as the OpenVPN user is 
  not supposed to be able to read the `vpn-server-api` configuration file

## 2.1.0 (2016-01-18)
- update the `client-connect` and `client-disconnect` scripts to log to a 
  database to keep track of connections to the server and expose it through the
  API

## 2.0.2 (2016-01-11)
- add bare bones scripts to be executed by OpenVPN process on client-connect
  and client-disconnect 

## 2.0.1 (2016-01-11)
- implement logging to syslog for API calls

## 2.0.0 (2016-01-05)
- major refactor of the code and dependencies
- new configuration format (YAML)
- better API
- add ability to disable common names using CCD
- get a list of disabled common names
- stricter input validation
- add more unit tests

## 1.1.0 (2015-12-22)
- add server info API call
- catch exception when socket connection does not work
- update README

## 1.0.1 (2015-12-16)
- update crlPath default value

## 1.0.0 (2015-12-11)
- initial release
