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

## OpenVPN

TBD

## CCD

TBD

## CRL

TBD

# Running

    php -S localhost:8080 -t web/

# API
Behind the title is the component the API calls are relevant for. The calls 
with OpenVPN are sent to ALL configured OpenVPN instances. The others are 
shared by all OpenVPN instances.

The OpenVPN calls return the response for the API request from all OpenVPN 
servers. The response is in the JSON format. The response is wrapped in an
`items` object that contain an array with responses from each of the OpenVPN 
servers. Each entry for a server also has an `ok` field which indicates 
whether the response from the server was handled correctly. If e.g. a server
is down, `ok` will be set to the boolean `false`. If the server is up and 
responded to the request the result will be `true`.

## Version (OpenVPN)
Retrieve the versions of the OpenVPN instances:

### Call

    GET /version

### Response
Here you can see two configured servers, one with the identifier `UDP` and one
with the identifier `TCP`. The `TCP` instance is down, or otherwise 
unavailable. Always check the `ok` field before accessing the responses, in 
this case in the `version` field.

    {
        "items": [
            {
                "id": "UDP",
                "ok": true,
                "version": "OpenVPN 2.3.8 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Aug  4 2015"
            },
            {
                "id": "TCP",
                "ok": false
            }
        ]
    }

## Load Statistics (OpenVPN)

### Call

    GET /load-stats

### Response

    {
        "items": [
            {
                "id": "UDP",
                "load-stats": {
                    "bytes_in": 3051316,
                    "bytes_out": 7112958,
                    "number_of_clients": 1
                },
                "ok": true
            }
        ]
    }

## Connection Status (OpenVPN)

### Call

    GET /status

### Response

    {
        "items": [
            {
                "id": "UDP",
                "ok": true,
                "status": [
                    {
                        "bytes_in": 60937,
                        "bytes_out": 63493,
                        "common_name": "fkooman_samsung_i9300",
                        "connected_since": 1451489134,
                        "real_address": "10.64.87.183:51565",
                        "virtual_address": [
                            "fd00:4242:4242::1003",
                            "10.42.42.5"
                        ]
                    }
                ]
            }
        ]
    }

## Kill Connection (OpenVPN)

The kill command will go to all OpenVPN instances, and you can see whether or
not a client was killed.

### Call

    POST /kill
        common_name=fkooman_samsung_i9300

### Response

Here no client was killed, note that the response is still `ok`, but it just
was not able to kill this particular client. The `kill` field here shows 
whether or not a client was killed:

    {
        "items": [
            {
                "id": "UDP",
                "kill": false,
                "ok": true
            }
        ]
    }

Here a client was actually killed:

    {
        "items": [
            {
                "id": "UDP",
                "kill": true,
                "ok": true
            }
        ]
    }

## Disable Configuration (CCD)

Disable a configuration for a particular CN.

### Call

    POST /ccd/disable
        common_name=foo_bar

### Response

## Enable Configuration (CCD)

Enable a configuration for a particular CN, this actually means the `disable`
command is removed from the CCD.

### Call

    POST /ccd/enable
        common_name=foo_bar

### Response

## Get Disabled Configurations (CCD)

Obtain a list of disabled CNs. Optionally you can use the query parameter 
`user_id` to filter the returned results.

### Call

    GET /ccd/disable?user_id=foo

### Response

## Certificate Revocation List (CRL)

This will fetch the CRL from a configured endpoint. This needs to be triggered
whenever a configuration is revoked.

### Call

    POST /crl/fetch

### Response

    {
        "ok": true
    }

Or in case of an error:

    {
        "error": "unable to download CRL",
        "ok": false
    }

# License
Licensed under the Apache License, Version 2.0;

   http://www.apache.org/licenses/LICENSE-2.0
