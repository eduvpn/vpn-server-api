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

use SURFnet\VPN\Common\Config;

$baseDir = dirname(__DIR__);
$configDir = sprintf('%s/config', $baseDir);

$instanceList = [];

foreach (glob(sprintf('%s/*', $configDir), GLOB_ONLYDIR) as $dirName) {
    $instanceId = basename($dirName);

    $configFile = sprintf('%s/%s/config.php', $configDir, $instanceId);
    $config = Config::fromFile($configFile);

    $instanceNumber = $config->getItem('instanceNumber');
    $instanceList[$instanceId] = [
        'instanceNumber' => $instanceNumber,
        'profileInfo' => [],
    ];

    $vpnProfiles = $config->getSection('vpnProfiles');
    $profileIdList = array_keys($vpnProfiles->toArray());
    foreach ($profileIdList as $profileId) {
        $profileNumber = $vpnProfiles->getSection($profileId)->getItem('profileNumber');
        $instanceList[$instanceId]['profileInfo'][] = [
            'profileId' => $profileId,
            'profileNumber' => $profileNumber,
        ];
    }
}

foreach ($instanceList as $k => $v) {
    echo sprintf('[%d] (%s)', $v['instanceNumber'], $k).PHP_EOL;
    foreach ($v['profileInfo'] as $p) {
        echo "\t".sprintf('[%d] (%s)', $p['profileNumber'], $p['profileId']).PHP_EOL;
    }
}
