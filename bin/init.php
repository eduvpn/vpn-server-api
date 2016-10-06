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

use SURFnet\VPN\Server\Api\OtpLog;
use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\FileIO;

try {
    $p = new CliParser(
        'Initialize the OTP key storage',
        [
            'instance' => ['the instance', true, true],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->e('help')) {
        echo $p->help();
        exit(0);
    }

    $dataDir = sprintf('%s/data/%s/users', dirname(__DIR__), $opt->v('instance'));

    // create VPN directory if it does not yet exist
    FileIO::createDir($dataDir, 0700);

    $db = new PDO(sprintf('sqlite://%s/otp.sqlite', $dataDir));
    $otpLog = new OtpLog($db);
    $otpLog->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
