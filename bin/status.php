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
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Server\OpenVpn\ManagementSocket;
use SURFnet\VPN\Server\OpenVpn\ServerManager;

try {
    $p = new CliParser(
        'Get the connection status of an instance',
        [
            'instance' => ['the VPN instance', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->hasItem('instance') ? $opt->getItem('instance') : 'default';

    $configFile = sprintf('%s/config/%s/config.php', dirname(__DIR__), $instanceId);
    $config = Config::fromFile($configFile);

    $serverManager = new ServerManager(
        $config,
        new ManagementSocket(),
        new Logger($argv[0])
    );

    $output = [
        sprintf('*** %s ***', $instanceId),
    ];

    foreach ($serverManager->connections() as $profile) {
        $output[] = $profile['id'];

        foreach ($profile['connections'] as $connection) {
            $output[] = sprintf("\t%s\t%s", $connection['common_name'], implode(', ', $connection['virtual_address']));
        }
    }

    echo implode(PHP_EOL, $output).PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
