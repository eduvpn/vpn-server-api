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

use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Server\InstanceConfig;
use SURFnet\VPN\Common\HttpClient\GuzzleHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use GuzzleHttp\Client;

$logger = new Logger(
    basename($argv[0])
);

$envData = [];

try {
    $envKeys = [
        'INSTANCE_ID',
        'POOL_ID',
        'common_name',
        'username',
        'password',
    ];

    // read environment variables
    foreach ($envKeys as $envKey) {
        $envValue = getenv($envKey);
        if (empty($envValue)) {
            throw new RuntimeException(sprintf('environment variable "%s" is not set', $envKey));
        }
        $envData[$envKey] = $envValue;
    }

    $instanceId = $envData['INSTANCE_ID'];
    $configDir = sprintf('%s/config/%s', dirname(__DIR__), $instanceId);
    $config = InstanceConfig::fromFile(
        sprintf('%s/config.yaml', $configDir)
    );

    // vpn-server-api
    $guzzleServerClient = new GuzzleHttpClient(
        $config->v('apiProviders', 'vpn-server-api', 'userName'),
        $config->v('apiProviders', 'vpn-server-api', 'userPass')
    );
    $serverClient = new ServerClient($guzzleServerClient, $config->v('apiProviders', 'vpn-server-api', 'apiUri'));

    $userId = explode('_', $envData['common_name'], 2)[0];
    $otpKey = $envData['password'];

    if (false === $serverClient->verifyOtpKey($userId, $otpKey)) {
        $envData['ok'] = false;
        $envData['password'] = '_STRIPPED_';
        $envData['error_msg'] = 'invalid OTP';
        $logger->error(json_encode($envData));
        exit(1);
    }

    $envData['ok'] = true;
    $envData['password'] = '_STRIPPED_';
    $logger->info(
        json_encode($envData)
    );
} catch (Exception $e) {
    $logger->error($e->getMessage());
    exit(1);
}
