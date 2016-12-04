#!/usr/bin/env php
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

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;
use Symfony\Component\Yaml\Yaml;

$credentials = [
    'vpn-user-portal' => bin2hex(random_bytes(16)),
    'vpn-admin-portal' => bin2hex(random_bytes(16)),
    'vpn-server-node' => bin2hex(random_bytes(16)),
];

try {
    $p = new CliParser(
        'Update the API secrets used by the various components',
        [
            'instance' => ['the instance to target, e.g. vpn.example', true, true],
            'prefix' => ['the prefix of the installed modules (DEFAULT: /usr/share)', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }
    $prefix = '/usr/share';
    if ($opt->e('prefix')) {
        $prefix = $opt->v('prefix');
    }

    $instanceId = $opt->v('instance');

    // api provider
    $configFile = sprintf('%s/vpn-server-api/config/%s/config.yaml', $prefix, $instanceId);
    $config = Config::fromFile($configFile);
    $configData = $config->v();
    $configData['apiConsumers']['vpn-user-portal'] = $credentials['vpn-user-portal'];
    $configData['apiConsumers']['vpn-admin-portal'] = $credentials['vpn-admin-portal'];
    $configData['apiConsumers']['vpn-server-node'] = $credentials['vpn-server-node'];
    Config::toFile($configFile, $configData);

    // consumers
    $consumerConfigFiles = [
        'vpn-user-portal' => sprintf('%s/vpn-user-portal/config/%s/config.yaml', $prefix, $instanceId),
        'vpn-admin-portal' => sprintf('%s/vpn-admin-portal/config/%s/config.yaml', $prefix, $instanceId),
        'vpn-server-node' => sprintf('%s/vpn-server-node/config/%s/config.yaml', $prefix, $instanceId),
    ];

    foreach ($consumerConfigFiles as $configId => $configFile) {
        if (@file_exists($configFile)) {
            $config = Config::fromFile($configFile);
            $configData = $config->v();
            $configData['apiProviders']['vpn-server-api']['userPass'] = $credentials[$configId];
            Config::toFile($configFile, $configData);
        }
    }
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
