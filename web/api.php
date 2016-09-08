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

use fkooman\Http\Request;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Service;
use SURFnet\VPN\Server\Config;
use SURFnet\VPN\Server\Logger;
use SURFnet\VPN\Server\InstanceConfig;
use SURFnet\VPN\Server\Api\CommonNamesModule;
use SURFnet\VPN\Server\Api\InfoModule;
use SURFnet\VPN\Server\Api\LogModule;
use SURFnet\VPN\Server\Api\OpenVpnModule;
use SURFnet\VPN\Server\Api\UsersModule;
use SURFnet\VPN\Server\Api\Users;
use SURFnet\VPN\Server\Api\CommonNames;
use SURFnet\VPN\Server\OpenVpn\ManagementSocket;
use SURFnet\VPN\Server\OpenVpn\ServerManager;

$logger = new Logger('vpn-server-api');

try {
    $request = new Request($_SERVER);

    // this actually uses SERVER_NAME (hardcoded in Apache), and not HTTP_HOST
    // that could be determined by client
    $instanceId = $request->getUrl()->getHost();

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $instanceId);
    $configDir = sprintf('%s/config/%s', dirname(__DIR__), $instanceId);

    $instanceConfig = InstanceConfig::fromFile(
        sprintf('%s/config.yaml', $configDir)
    );

    $apiConfig = Config::fromFile(
        sprintf('%s/api.yaml', $configDir)
    );

    $poolList = [];
    foreach ($instanceConfig->pools() as $poolId) {
        $poolList[$poolId] = $instanceConfig->pool($poolId);
    }

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register(
        new BearerAuthentication(
            new ArrayBearerValidator(
                $apiConfig->v('api')
            ),
            ['realm' => 'VPN Server API']
        ),
        'api'
    );
    $service = new Service();
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);

    $service->addModule(
        new LogModule($dataDir)
    );
    $service->addModule(
        new OpenVpnModule(
            new ServerManager($instanceConfig, new ManagementSocket(), $logger)
        )
    );
    $service->addModule(
        new CommonNamesModule(
            new CommonNames(sprintf('%s/common_names', $dataDir)),
            $logger
        )
    );
    $service->addModule(
        new UsersModule(
            new Users(sprintf('%s/users', $dataDir)),
            $logger
        )
    );
    $service->addModule(
        new InfoModule($poolList)
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getJsonResponse()->send();
}
