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
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Server\Stats;
use SURFnet\VPN\Server\Storage;

try {
    $p = new CliParser(
        'Generate statistics for an instance',
        [
            'instance' => ['the VPN instance', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->e('instance') ? $opt->v('instance') : 'default';

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $instanceId);
    $db = new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir));
    $storage = new Storage($db, new DateTime('now'));

    $outFile = sprintf('%s/stats.json', $dataDir);

    $stats = new Stats(new DateTime());
    $statsData = $stats->get($storage->getAllLogEntries());

    FileIO::writeJsonFile(
        $outFile,
        $statsData
    );
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
