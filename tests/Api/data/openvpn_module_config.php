<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

return  [
  'apiConsumers' => [
    'vpn-user-portal' => 'abcdef',
    'vpn-server-node' => 'aabbcc',
  ],
  'CA' => [
    'key_size' => 4096,
    'ca_expire' => 1826,
    'cert_expire' => 365,
    'ca_cn' => 'VPN CA',
  ],
  'instanceNumber' => 1,
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
  'groupProviders' => [
    'StaticProvider' => [
      'all' => [
        'displayName' => 'All',
        'members' => [
          0 => 'foo',
          1 => 'bar',
        ],
      ],
      'students' => [
        'displayName' => 'Students',
        'members' => [
          0 => 'foo',
        ],
      ],
      'employees' => [
        'displayName' => 'Employees',
        'members' => [
          0 => 'bar',
        ],
      ],
    ],
  ],
];
