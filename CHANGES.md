# Changelog

## 1.2.7 (2018-04-05)
- fix test with updated `eduvpn/common`

## 1.2.6 (2018-03-15)
- allow different certificate expiry days for client and server certificates, 
  they will also immediately be used, not requiring a new "init" of the CA. 
  By default server cert expiry will be 365 days, client cert will be 180 days
  (issue #66)

## 1.2.5 (2018-02-26)
- introduce `exposedVpnProtoPorts` to allow listing different protocols/ports
  from what the OpenVPN processes listen on, e.g. for `tcp/443` port sharing

## 1.2.4 (2018-02-25)
- change default expiry of (server/client) certificates to 180 days
- enable `tlsCrypt` dropping 2.3 client support for new deploys, will keep 
  working for existing deploys

## 1.2.3 (2018-01-17)
- make quad9 the default DNS for new deploys
- split out statistics per profile instead of "global"

## 1.2.2 (2017-12-14)
- update `eduvpn/common`
- fix test for new `christian-riesen/otp`

## 1.2.1 (2017-12-13)
- cleanup autoloading
- update `eduvpn/common`
- update embedded easy-rsa
- make YubiKey validating more robust and simplify code

## 1.2.0 (2017-11-28)
- switch to `cn` attribute to retrieve name of group(s) instead of 
  `description`
- update `fkooman/oauth-client` to 
  [6.0.0](https://github.com/fkooman/php-oauth2-client/blob/master/CHANGES.md#600-2017-11-27)
- update LDAP configuration example
- support Active Directory for retrieving group membership from LDAP

## 1.1.1 (2017-11-24)
- make it possible to configure binding to LDAP before retrieving group
  membership

## 1.1.0 (2017-11-23)
- sort profile config for "Info" page in admin portal
- LDAP ACL Provider

## 1.0.7 (2017-11-20)
- support disabling compression, disable by default for new deploys
  - *NOTE* changing this with client configurations in the field WILL break 
    them!

## 1.0.6 (2017-10-30)
- remove `--reject4` and `--reject6` arguments from `update-ip` script
- refactor code to ease RPM/DEB packaging

## 1.0.5 (2017-10-23)
- handle VOOT error more gracefully now
- remove `fkooman/secookie` requirement
- update unit test for new `eduvpn/common`

## 1.0.4 (2017-10-04)
- fix security issue with 2FA where any YubiKey OTP would be accepted to 
  connect to VPN service when user was not enrolled for YubiKey 2FA
- update default range6 config option to be a /64

## 1.0.3 (2017-09-19)
- increase TOTP attempt count to 60 from 10 per hour (issue #64)
- fix source formatting and method annotations

## 1.0.2 (2017-07-23)
- another attempt at fixing #62

## 1.0.1 (2017-07-21)
- fix parsing connections at OpenVPN processes when clients are slow to connect
  (#62)

## 1.0.0 (2017-07-13)
- initial release
