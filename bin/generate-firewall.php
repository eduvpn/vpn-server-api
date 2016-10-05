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
use SURFnet\VPN\Common\CliParser;

try {
    $p = new CliParser(
        'Generate firewall rules for all instances',
        [
            'install' => ['install the firewall', false, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
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

    if ($opt->e('install')) {
        FileIO::writeFile('/etc/sysconfig/iptables', $firewall, 0600);
        FileIO::writeFile('/etc/sysconfig/ip6tables', $firewall6, 0600);
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
    exit(1);
}
