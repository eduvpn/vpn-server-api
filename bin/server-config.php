#!/usr/bin/php
<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Server\InstanceConfig;
use SURFnet\VPN\Server\Config\OpenVpn;
use SURFnet\VPN\Common\CliParser;

try {
    $p = new CliParser(
        'Generate VPN server configuration for an instance',
        [
            'instance' => ['the instance', true, true],
            'generate' => ['generate a new certificate for the server', false, false],
            'cn' => ['the CN of the certificate to generate', true, false],
            'dh' => ['the length of DH keys, defaults to 3072', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->v('instance');
    $generateCerts = $opt->e('generate');
    $dhLength = $opt->e('dh') ? $opt->v('dh') : 3072;

    $configFile = sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $instanceId);
    $config = InstanceConfig::fromFile($configFile);

    $vpnConfigDir = sprintf('%s/openvpn-config', dirname(__DIR__));
    $vpnTlsDir = sprintf('%s/openvpn-config/tls/%s', dirname(__DIR__), $instanceId);

    $o = new OpenVpn($vpnConfigDir, $vpnTlsDir);
    $o->write($instanceId, $config);
    if ($generateCerts) {
        $userName = $config->v('apiProviders', 'vpn-ca-api', 'userName');
        $userPass = $config->v('apiProviders', 'vpn-ca-api', 'userPass');
        $apiUri = $config->v('apiProviders', 'vpn-ca-api', 'apiUri');
        $o->generateKeys($apiUri, $userName, $userPass, $opt->v('cn'), $dhLength);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
