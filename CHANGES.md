# Changelog

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
