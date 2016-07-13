[![Build Status](https://travis-ci.org/eduvpn/vpn-server-api.svg?branch=master)](https://travis-ci.org/eduvpn/vpn-server-api)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/eduvpn/vpn-server-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/eduvpn/vpn-server-api/?branch=master)

# Introduction

This service runs on the same server as the OpenVPN instance(s) and is able to
control them.

# Features

- Manage various OpenVPN instances running on the same machine, e.g. UDP/TCP 
  instances
- Get a list of currently connected clients
- Disconnect a client
- Access to connection log

# Deployment

See the [documentation](https://github.com/eduvpn/documentation) repository.

# Development

    $ composer install
    $ cp config/config.yaml.example config/config.yaml

The defaults in this file, and in the other files are for the 
production deployment setup and will not work well for development.

    $ cp config/pools.yaml.example config/pools.yaml

Point `configDir` to a writable directory, and optionally modify
the IP configuration.

    $ cp config/log.yaml.example config/log.yaml

Point `log/dsn` to a writable file.

    $ mkdir data
    $ php bin/init
    $ php -S localhost:8009 -t web/

# Authentication

The API is protected using Bearer tokens. There are various "clients" 
configured as can be seen in the configuration file together with their 
permissions. See `config/config.yaml` for the defaults.

# License
Licensed under the Apache License, Version 2.0;

   http://www.apache.org/licenses/LICENSE-2.0
