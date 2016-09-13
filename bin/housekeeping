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

use fkooman\VPN\Server\OtpLog;
use fkooman\Config\Reader;
use fkooman\Config\YamlFile;

function cleanupOtp($dbPath)
{
    $db = new PDO($dbPath);
    $otpLog = new OtpLog($db);
    // remove all OTP key entries that are older than 5 minutes
    $otpLog->housekeeping(strtotime('now -5 minutes'));
}

try {
    $configReader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );
    $vpnDataDir = $configReader->v('vpnDataDir');

    foreach ($configReader->v('instanceList') as $instance) {
        $instanceName = $instance['hostName'];
        $dbPath = sprintf('sqlite://%s/%s/otp.sqlite', $vpnDataDir, $instanceName);
        cleanupOtp($dbPath);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
