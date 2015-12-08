# Introduction
This service runs on the OpenVPN instances to control their behavior. It will
implement the following API calls:

Implemented:
* retrieve a list of connected clients
* disconnect a connected client

TODO:
* prevent clients from connecting 
* unprevent clients from connecting
* trigger CRL refresh

# Configuration
To generate a password for `config/config.ini`, use this and replace `s3cr3t` 
with your password:

    php -r "echo password_hash('s3cr3t', PASSWORD_DEFAULT) . PHP_EOL;"

# API

## Status

    $ curl -u foo:bar http://localhost:8080/api.php/status

## Disconnect

    $ curl -u foo:bar -d 'config_id=foo_bar' http://localhost:8080/api.php/disconnect

## ...

# License
Licensed under the Apache License, Version 2.0;

   http://www.apache.org/licenses/LICENSE-2.0
