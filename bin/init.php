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
use SURFnet\VPN\Server\CA\EasyRsaCa;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\TlsAuth;

try {
    $p = new CliParser(
        'Initialize the CA',
        [
            'instance' => ['the instance', true, true],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $configFile = sprintf('%s/config/%s/config.yaml', dirname(__DIR__), $opt->v('instance'));
    $config = Config::fromFile($configFile);

    $easyRsaDir = sprintf('%s/easy-rsa', dirname(__DIR__));
    $easyRsaDataDir = sprintf('%s/data/%s/easy-rsa', dirname(__DIR__), $opt->v('instance'));

    $ca = new EasyRsaCa($easyRsaDir, $easyRsaDataDir);
    $ca->init($config);

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $opt->v('instance'));
    $storage = new Storage(new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)));
    $storage->init();

    $tlsAuth = new TlsAuth($dataDir);
    $tlsAuth->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
