[![Build Status](https://travis-ci.org/eduVPN/vpn-server-api.svg?branch=master)](https://travis-ci.org/eduVPN/vpn-server-api)
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
- provision a client specific configuration (CCD):
  - disable a particular CN
- trigger a CRL reload from the CA to prevent revoked client configurations 
  from being used in new connections

The service makes this functionality available through a HTTP API.

# Configuration

You can modify the configuration in the `config/config.yaml`, see 
`config/config.yaml.example` for the example.

## Authentication

The user name here is `admin` and the password is the 
[password_hash](https://secure.php.net/password_hash) of `s3cr3t`.

    Users:
        admin: $2y$10$uA77iLeDLaU.a.KRuhUYKuziuZacuWzodpqLok2XBQLXvig6UaB9a


## OpenVPN
Here you configure the OpenVPN instances running on this machine. The socket
points to the TCP management session of the particular OpenVPN instance.

    OpenVpn:
        - { socket: 'tcp://localhost:7505', id: udp_1194 }
        - { socket: 'tcp://localhost:7506', id: tcp_443 }

## CCD
Here you configure the path where the CCD files need to be written to. This 
needs to be writable by this service, and also the OpenVPN instances need to
be configured to use this with `--client-config-dir`.

    Ccd:
        path: /var/lib/vpn-server-api/ccd

## CRL
Here you configure the URL where the CRL will be available and the path where
the CRL will be written to. The path needs to be writable by this service and
the OpenVPN instances need to be configured to use the CRL at this path using 
`--crl-verify`.

    Crl:
        url: http://localhost/vpn-config-api/api.php/ca.crl
        path: /var/lib/vpn-server-api

# Running

    php -S localhost:8080 -t web/

# API
Behind the section headings below the component for which the particular call
is relevant for is listed. The calls with OpenVPN are sent to ALL configured 
OpenVPN instances. The others are shared by all OpenVPN instances.

The OpenVPN calls return the response for the API request from all OpenVPN 
servers. The response is in the JSON format. The response is wrapped in an
`items` object that contain an array with responses from each of the OpenVPN 
servers. Each entry for a server also has an `ok` field which indicates 
whether the response from the server was handled correctly. If e.g. a server
is down, `ok` will be set to the boolean `false`. If the server is up and 
responded to the request the result will be `true`. Callers MUST first check
the value of this `ok` field before assuming the rest of the response is 
available.

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

## Get Disabled Configurations (CCD)

Obtain a list of disabled CNs. Optionally you can use the query parameter 
`user_id` to filter the returned results and show only disabled CNs for a 
particular user.

### Call

    GET /ccd/disable?user_id=foo

### Response

This shows that no configurations are currently disabled for this user.

    {
        "disabled": [],
        "ok": true
    }

Now, `foo_bar` is disabled:

    {
        "disabled": [
            "foo_bar"
        ],
        "ok": true
    }

## Disable Configuration (CCD)

Disable a configuration for a particular CN.

### Call

    POST /ccd/disable
        common_name=foo_bar

### Response

The response will be `true` for the `ok` field if the provided `common_name` 
was actually disabled. It will be `false` if it was already disabled.

    {
        "ok": true
    }

## Enable Configuration (CCD)

Enable a configuration for a particular CN, this actually means the `disable`
command is removed from the CCD.

### Call

    DELETE /ccd/disable?common_name=foo_bar

### Response

The response will be `true` for the `ok` field if the provided `common_name` 
was enabled again. It will be `false` if was not disabled.

    {
        "ok": true
    }

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
