# Configuration Changes

This document describes all configuration file changes since the 2.0.0 release.
This in order to keep track of all changes that were made during the 2.x 
release cycle. 

This will help upgrades to a future 3.x release. Configuration changes during
the 2.x life cycle are NOT required. Any existing configuration file will keep
working!

## 2.0.2

- Allow setting the DNS (search) suffix(es) the client will use using the 
  `dnsSuffix` option. It takes an `array` with domain name(s). The default is 
  the empty array `[]` meaning there will be no DNS suffix pushed to the client

## 2.0.1

_N/A_
