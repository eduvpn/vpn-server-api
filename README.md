[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/eduVPN/vpn-server-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/eduVPN/vpn-server-api/?branch=master)

# Introduction

This service runs on the same server as the OpenVPN instance(s) and is able to
control them.

It implements the following features:
- access the OpenVPN management interface:
  - get version information
  - get current load statistics
  - get connection status (list currently connected clients)
  - kill active connections
- provision a client specific configuration (CCD)
- trigger a CRL reload from the CA to prevent revoked client configurations 
  from being used

The service makes this functionality available through a HTTP API.

# Configuration

## Authentication

TBD

## Management Interface

TBD

## CCD

TBD

## CRL

TBD

# API

## Version

    GET /version

## Load Statistics

    GET /load-stats

## Connection Status

    GET /status

## Kill Connection

    POST /kill
        common_name=foo_bar

## Disable Configuration

Disable a configuration for a particular CN.

    POST /ccd/disable
        common_name=foo_bar

## Enable Configuration

Enable a configuration for a particular CN, this actually means the `disable`
command is removed from the CCD.

    POST /ccd/enable
        common_name=foo_bar

## Get Disabled Configurations

Obtain a list of disabled CNs. Optionally you can use the query parameter 
`commonNameStartsWith` to filter the returned results.

    GET /ccd/disable?commonNameStartsWith=foo_

## Certificate Revocation List

    POST /crl/refresh

# Running

    php -S localhost:8080 -t web/

# License
Licensed under the Apache License, Version 2.0;

   http://www.apache.org/licenses/LICENSE-2.0
