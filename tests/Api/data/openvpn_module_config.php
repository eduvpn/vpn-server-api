<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

return  [
  'apiConsumers' => [
    'vpn-user-portal' => 'abcdef',
    'vpn-server-node' => 'aabbcc',
  ],
  'vpnProfiles' => [
    'internet' => [
      'profileNumber' => 1,
      'displayName' => 'Internet Access',
      'extIf' => 'eth0',
      'vpnProtoPorts' => ['udp/1194'],
      'range' => '10.0.0.0/24',
      'range6' => 'fd00:4242:4242::/48',
      'hostName' => 'vpn.example',
    ],
  ],
];
