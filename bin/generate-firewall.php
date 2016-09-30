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

use SURFnet\VPN\Server\Config\Firewall;
use SURFnet\VPN\Server\InstanceConfig;
use SURFnet\VPN\Common\FileIO;

function showHelp(array $argv)
{
    return implode(
        PHP_EOL,
        [
            sprintf('SYNTAX: %s [--install]', $argv[0]),
            '',
            '--install  install the firewall rules',
            '',
        ]
    );
}

try {
    $installFw = false;

    for ($i = 1; $i < $argc; ++$i) {
        if ('--help' === $argv[$i] || '-h' === $argv[$i]) {
            echo showHelp($argv);
            exit(0);
        }
        if ('--install' === $argv[$i] || '-i' === $argv[$i]) {
            $installFw = true;
        }
    }

    // detect all instances
    $configList = [];
    $configDir = sprintf('%s/config', dirname(__DIR__));
    foreach (glob(sprintf('%s/*', $configDir), GLOB_ONLYDIR | GLOB_ERR) as $instanceDir) {
        $instanceId = basename($instanceDir);
        $configList[$instanceId] = InstanceConfig::fromFile(sprintf('%s/%s/config.yaml', $configDir, $instanceId));
    }

    $firewall = Firewall::getFirewall4($configList);
    $firewall6 = Firewall::getFirewall6($configList);

    if ($installFw) {
        FileIO::writeFile('/etc/sysconfig/iptables', $firewall);
        FileIO::writeFile('/etc/sysconfig/ip6tables', $firewall6);
    } else {
        echo '##########################################'.PHP_EOL;
        echo '# IPv4'.PHP_EOL;
        echo '##########################################'.PHP_EOL;
        echo $firewall;

        echo '##########################################'.PHP_EOL;
        echo '# IPv6'.PHP_EOL;
        echo '##########################################'.PHP_EOL;
        echo $firewall6;
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    echo $e->getTraceAsString();
    exit(1);
}
