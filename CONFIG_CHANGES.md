# Configuration Changes

This document describes all configuration file changes since the 2.0.0 release.
This in order to keep track of all changes that were made during the 2.x 
release cycle. 

This will help upgrades to a future 3.x release. Configuration changes during
the 2.x life cycle are NOT required. Any existing configuration file will keep
working!

## 2.2.10

- the `tlsProtection` configuration option was removed. It will always be 
  `tls-crypt`. Specifying, or not specifying it will have no effect

## 2.2.7

- the generated CA key type configurable with the `vpnCaKeyType` configuration 
  option. The default is `RSA`, but `ECDSA` (NIST P-256) and `EdDSA` (Ed25519) 
  are also supported. Note that `EdDSA` is only supported on Fedora and 
  Debian >= 10 servers. We did not do extensive tests regarding client support 
  after publishing 
  [this](https://www.eduvpn.org/blog/openvpn_modern_crypto.html) blog post. 
  **USE WITH CARE, THIS IS NOT OFFICIALLY SUPPORTED YET!** 

## 2.2.5

- the `useVpnDaemon` option got a sibling: `vpnDaemonTls` which defaults to 
  `true`. This means that by default VPN Daemon connections to IP addresses as
  specified in `managementIp` other than `127.0.0.1`, or `::1`, will require 
  TLS. Setting it to `false` disables TLS. This is useful when you have 
  multiple nodes in the same data center where TLS between controller and node 
  is not that important.
  
## 2.2.0

- the option `vpnCaPath` now defaults to `/usr/bin/vpn-ca`, when not specified,
  since switching to [vpn-ca](https://github.com/letsconnectvpn/vpn-ca) by
  default. You can still override this, but that is mostly useful for 
  development purposes. The option can be removed for normal deployments.

## 2.1.0

- allow enabling [vpn-daemon](https://github.com/letsconnectvpn/vpn-daemon) 
  support with the `useVpnDaemon` option that takes a boolean. When the option 
  is NOT specified it defaults to `false`. This is a GLOBAL option, so all 
  profiles will use the daemon to talk to OpenVPN processes.

## 2.0.4

- allow specifying the `tlsOneThree` profile option, taking a `bool` to force 
  the server and client to use TLSv1.3. **NOTE** this only works when both the
  server and client(s) use OpenSSL >= 1.1.1. This is **FOR TESTING ONLY**. See 
  the [eduVPN Blog](https://www.eduvpn.org/blog/openvpn_modern_crypto.html) for
  more information.

## 2.0.3

- allow using [vpn-ca](https://github.com/fkooman/vpn-ca), the experimental CA 
  for use with Let's Connect!/eduVPN. The `vpnCaPath` configuration option was
  added. When specifying the (absolute) path to the `vpn-ca` binary vpn-ca will 
  be used and migrate the existing EasyRsa CA database

## 2.0.2

- Allow setting the DNS (search) suffix(es) the client will use using the 
  `dnsSuffix` option. It takes an `array` with domain name(s). The default is 
  the empty array `[]` meaning there will be no DNS suffix pushed to the client
