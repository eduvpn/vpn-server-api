# Changelog

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
