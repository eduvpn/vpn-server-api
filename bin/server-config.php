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

function showHelp(array $argv)
{
    return implode(
        PHP_EOL,
        [
            sprintf('SYNTAX: %s [--instance domain.tld] [--generate domain.tld]', $argv[0]),
            '',
            '--instance domain.tld      the VPN instance to write configuration files',
            '                           for',
            '--generate domain.tld      generate new certificates/keys for this instance,',
            '                           a name can only be used once!',
            '',
        ]
    );
}

try {
    $instanceId = null;
    $generateCerts = false;
    $serverCn = null;
    $dhLength = 3072;

    for ($i = 0; $i < $argc; ++$i) {
        if ('--help' == $argv[$i] || '-h' === $argv[$i]) {
            echo showHelp($argv);
            exit(0);
        }

        if ('--instance' === $argv[$i] || '-i' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $instanceId = $argv[$i + 1];
                ++$i;
            }
        }

        if ('--generate' === $argv[$i] || '-g' === $argv[$i]) {
            $generateCerts = true;
            if (array_key_exists($i + 1, $argv)) {
                $serverCn = $argv[$i + 1];
                ++$i;
            }
        }

        // undocumented on purpose, override the dh length
        if ('--dh' === $argv[$i]) {
            if (array_key_exists($i + 1, $argv)) {
                $dhLength = $argv[$i + 1];
                ++$i;
            }
        }
    }

    if (is_null($instanceId)) {
        throw new RuntimeException('instance must be specified, see --help');
    }

    if ($generateCerts && is_null($serverCn)) {
        throw new RuntimeException('cannot generate certificates without specifying server CN');
    }

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
        $o->generateKeys($apiUri, $userName, $userPass, $serverCn, $dhLength);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
