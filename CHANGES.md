# Changelog

## 3.2.0 (...)
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
