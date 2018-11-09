<?php return array (
  'apiConsumers' => 
  array (
    'vpn-user-portal' => '43378e150cca493ea257459ee2034ae4',
    'vpn-admin-portal' => '2932f45cba53ccff514dc750229220af',
    'vpn-server-node' => 'c690743c26cbf2c245afb4b1ed276c50',
  ),
  'CA' => 
  array (
    'key_size' => 4096,
    'ca_expire' => 1826,
    'cert_expire' => 180,
    'ca_cn' => 'VPN CA',
  ),
  'instanceNumber' => 1,
  'vpnProfiles' => 
  array (
    'students' => 
    array (
      'profileNumber' => 1,
      'displayName' => 'RU Services Students',
      'extIf' => 'eth0',
      'enableCompression' => false,
      'range' => '195.169.216.0/22',
      'range6' => 'fd68:73b3:5c82:f680::/60',
      'hostName' => 'students.ru.eduvpn.nl',
      'listen' => '::',
      'managementIp' => '127.0.0.1',
      'enableNat4' => false,
      'enableNat6' => false,
      'reject4' => false,
      'reject6' => true,
      'defaultGateway' => true,
      'tlsProtection' => false,
      'routes' => 
      array (
      ),
      'dns' => 
      array (
        0 => '131.174.117.20',
      ),
      'twoFactor' => false,
      'clientToClient' => false,
      'enableLog' => false,
      'enableAcl' => false,
      'aclGroupList' => 
      array (
      ),
      'blockSmb' => false,
      'vpnProtoPorts' => 
      array (
        0 => 'udp/1200',
        1 => 'tcp/1200',
        2 => 'udp/1201',
        3 => 'tcp/1201',
        4 => 'udp/1202',
        5 => 'tcp/1202',
        6 => 'udp/1203',
        7 => 'tcp/1203',
        8 => 'udp/1204',
        9 => 'tcp/1204',
        10 => 'udp/1205',
        11 => 'tcp/1205',
        12 => 'udp/1206',
        13 => 'tcp/1206',
        14 => 'udp/1207',
        15 => 'tcp/1207',
      ),
      'hideProfile' => false,
    ),
    'employees' => 
    array (
      'profileNumber' => 2,
      'displayName' => 'RU Services Employees',
      'extIf' => 'eth0',
      'enableCompression' => false,
      'range' => '195.169.220.0/22',
      'range6' => 'fd17:21a3:5439:7c60::/60',
      'hostName' => 'employees.ru.eduvpn.nl',
      'listen' => '::',
      'managementIp' => '127.0.0.1',
      'enableNat4' => true,
      'enableNat6' => false,
      'reject4' => false,
      'reject6' => true,
      'defaultGateway' => true,
      'tlsCrypt' => true,
      'routes' => 
      array (
      ),
      'dns' => 
      array (
        0 => '131.174.117.20',
      ),
      'twoFactor' => false,
      'clientToClient' => false,
      'enableLog' => false,
      'enableAcl' => false,
      'aclGroupList' => 
      array (
      ),
      'blockSmb' => false,
      'vpnProtoPorts' => 
      array (
        0 => 'udp/1208',
        1 => 'tcp/1208',
        2 => 'udp/1209',
        3 => 'tcp/1209',
        4 => 'udp/1210',
        5 => 'tcp/1210',
        6 => 'udp/1211',
        7 => 'tcp/1211',
        8 => 'udp/1212',
        9 => 'tcp/1212',
        10 => 'udp/1213',
        11 => 'tcp/1213',
        12 => 'udp/1214',
        13 => 'tcp/1214',
        14 => 'udp/1215',
        15 => 'tcp/1215',
      ),
      'hideProfile' => false,
    ),
    'isc-admins' => 
    array (
      'profileNumber' => 3,
      'displayName' => 'RU ISC Admins',
      'extIf' => 'eth0',
      'enableCompression' => false,
      'range' => '195.169.209.0/24',
      'range6' => 'fd5f:cadf:4264:1f30::/60',
      'hostName' => 'isc-admins.ru.eduvpn.nl',
      'listen' => '::',
      'managementIp' => '127.0.0.1',
      'enableNat4' => false,
      'enableNat6' => false,
      'reject4' => false,
      'reject6' => true,
      'defaultGateway' => true,
      'tlsCrypt' => true,
      'routes' => 
      array (
      ),
      'dns' => 
      array (
        0 => '131.174.117.20',
      ),
      'twoFactor' => false,
      'clientToClient' => false,
      'enableLog' => false,
      'enableAcl' => true,
      'aclGroupList' => 
      array (
        0 => 'urn:collab:group:surfteams.nl:nl:surfnet:diensten:ru-secure-test',
      ),
      'blockSmb' => false,
      'vpnProtoPorts' => 
      array (
        0 => 'udp/1216',
        1 => 'tcp/1216',
        2 => 'udp/1217',
        3 => 'tcp/1217',
      ),
      'hideProfile' => false,
    ),
    'test' => 
    array (
      'profileNumber' => 4,
      'displayName' => 'RU TEST',
      'extIf' => 'eth0',
      'enableCompression' => false,
      'range' => '195.169.208.128/25',
      'range6' => 'fd2d:a0f3:4ea8:7cc0::/60',
      'hostName' => 'test.ru.eduvpn.nl',
      'listen' => '::',
      'managementIp' => '127.0.0.1',
      'enableNat4' => false,
      'enableNat6' => false,
      'reject4' => false,
      'reject6' => true,
      'defaultGateway' => true,
      'tlsCrypt' => true,
      'routes' => 
      array (
      ),
      'dns' => 
      array (
        0 => '131.174.117.20',
      ),
      'twoFactor' => true,
      'clientToClient' => false,
      'enableLog' => false,
      'enableAcl' => true,
      'aclGroupList' => 
      array (
        0 => 'urn:collab:group:surfteams.nl:nl:surfnet:diensten:ru_eduvpn_test',
      ),
      'blockSmb' => false,
      'vpnProtoPorts' => 
      array (
        0 => 'udp/1224',
        1 => 'tcp/1224',
      ),
      'hideProfile' => false,
    ),
    'guests' => 
    array (
      'profileNumber' => 5,
      'displayName' => 'RU Services Guests',
      'extIf' => 'eth0',
      'enableCompression' => false,
      'range' => '195.169.208.0/25',
      'range6' => 'fd9c:a42e:8294:2fb0::/60',
      'hostName' => 'guests.ru.eduvpn.nl',
      'listen' => '::',
      'managementIp' => '127.0.0.1',
      'enableNat4' => false,
      'enableNat6' => false,
      'reject4' => false,
      'reject6' => true,
      'defaultGateway' => true,
      'tlsCrypt' => true,
      'routes' => 
      array (
      ),
      'dns' => 
      array (
        0 => '131.174.117.20',
      ),
      'twoFactor' => false,
      'clientToClient' => false,
      'enableLog' => false,
      'enableAcl' => false,
      'aclGroupList' => 
      array (
      ),
      'blockSmb' => false,
      'vpnProtoPorts' => 
      array (
        0 => 'udp/1232',
        1 => 'tcp/1232',
      ),
      'hideProfile' => false,
    ),
    'science' => 
    array (
      'profileNumber' => 6,
      'displayName' => 'RU Faculty of Science',
      'extIf' => 'eth0',
      'enableCompression' => false,
      'range' => '195.169.210.0/23',
      'range6' => 'fda5:b7aa:cbd3:2590::/60',
      'hostName' => 'science.ru.eduvpn.nl',
      'listen' => '::',
      'managementIp' => '127.0.0.1',
      'enableNat4' => false,
      'enableNat6' => false,
      'reject4' => false,
      'reject6' => true,
      'defaultGateway' => true,
      'tlsCrypt' => true,
      'routes' => 
      array (
      ),
      'dns' => 
      array (
        0 => '131.174.117.20',
      ),
      'twoFactor' => true,
      'clientToClient' => false,
      'enableLog' => false,
      'enableAcl' => false,
      'aclGroupList' => 
      array (
      ),
      'blockSmb' => false,
      'vpnProtoPorts' => 
      array (
        0 => 'udp/1240',
        1 => 'tcp/1240',
        2 => 'udp/1241',
        3 => 'tcp/1241',
        4 => 'udp/1242',
        5 => 'tcp/1242',
        6 => 'udp/1243',
        7 => 'tcp/1243',
      ),
      'hideProfile' => false,
    ),
    'donders' => 
    array (
      'profileNumber' => 7,
      'displayName' => 'RU FC Donders',
      'extIf' => 'eth0',
      'enableCompression' => false,
      'range' => '195.169.212.0/23',
      'range6' => 'fdfc:a687:2a3a:44d0::/60',
      'hostName' => 'donders.ru.eduvpn.nl',
      'listen' => '::',
      'managementIp' => '127.0.0.1',
      'enableNat4' => false,
      'enableNat6' => false,
      'reject4' => false,
      'reject6' => true,
      'defaultGateway' => true,
      'tlsCrypt' => true,
      'routes' => 
      array (
      ),
      'dns' => 
      array (
        0 => '131.174.117.20',
      ),
      'twoFactor' => true,
      'clientToClient' => false,
      'enableLog' => false,
      'enableAcl' => false,
      'aclGroupList' => 
      array (
      ),
      'blockSmb' => false,
      'vpnProtoPorts' => 
      array (
        0 => 'udp/1248',
        1 => 'tcp/1248',
        2 => 'udp/1249',
        3 => 'tcp/1249',
        4 => 'udp/1250',
        5 => 'tcp/1250',
        6 => 'udp/1251',
        7 => 'tcp/1251',
      ),
      'hideProfile' => false,
    ),
  ),
  'groupProviders' => 
  array (
    'VootProvider' => 
    array (
      'clientId' => 'ru.eduvpn.nl',
      'clientSecret' => 'XYZ',
      'authorizationEndpoint' => 'https://authz.surfconext.nl/oauth/authorize',
      'tokenEndpoint' => 'https://authz.surfconext.nl/oauth/token',
      'apiUrl' => 'https://voot.surfconext.nl/me/groups',
    ),
  ),
);