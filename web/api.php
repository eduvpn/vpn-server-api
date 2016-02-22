<?php

/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Config\Reader;
use fkooman\Config\YamlFile;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use fkooman\Rest\Service;
use fkooman\VPN\Server\Ca\CaModule;
use fkooman\VPN\Server\Ca\CrlFetcher;
use fkooman\VPN\Server\Config\ConfigModule;
use fkooman\VPN\Server\Config\StaticConfig;
use fkooman\VPN\Server\Config\IP;
use fkooman\VPN\Server\Log\ConnectionLog;
use fkooman\VPN\Server\Log\LogModule;
use fkooman\VPN\Server\OpenVpn\OpenVpnModule;
use fkooman\VPN\Server\OpenVpn\ServerApi;
use fkooman\VPN\Server\OpenVpn\ServerManager;
use fkooman\VPN\Server\OpenVpn\ServerSocket;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;

try {
    $caReader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    $openVpnReader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    $configReader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/client.yaml')
    );

    $logReader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/client.yaml')
    );

    // handles fetching the certificate revocation list
    $crlFetcher = new CrlFetcher(
        $caReader->v('Crl', 'url'),
        $caReader->v('Crl', 'path')
    );

    $ipRange = new IP($configReader->v('IPv4', 'ipRange', false, '10.10.10.0/24'));
    $poolRange = new IP($configReader->v('IPv4', 'poolRange', false, '10.10.10.128/25'));

    // handles the client configuration directory
    $staticConfig = new StaticConfig(
        $configReader->v('IPv4', 'staticConfigDir', false, sprintf('%s/data/static', dirname(__DIR__))),
        $ipRange,
        $poolRange
    );

    // handles the connection to the various OpenVPN instances
    $serverManager = new ServerManager();
    foreach ($openVpnReader->v('OpenVpn') as $openVpnServer) {
        $serverManager->addServer(
            new ServerApi(
                $openVpnServer['id'],
                new ServerSocket($openVpnServer['socket'])
            )
        );
    }

    // handles the connection history log
    try {
        $db = new PDO(
            $logReader->v('Log', 'dsn', false, 'sqlite:/var/lib/openvpn/log.sqlite'),
            $logReader->v('Log', 'username', false),
            $logReader->v('Log', 'password', false)
        );
        $connectionLog = new ConnectionLog($db);
    } catch (PDOException $e) {
        // unable to connect to database, so we continue without being able
        // to view the log
        syslog(LOG_ERR, $e->__toString());
        $connectionLog = null;
    }

    $logger = new Logger('vpn-server-api');
    $syslog = new SyslogHandler('vpn-server-api', 'user');
    $formatter = new LineFormatter();
    $syslog->setFormatter($formatter);
    $logger->pushHandler($syslog);

    // http request router
    $service = new Service();

    $apiAuth = new BasicAuthentication(
        function ($userId) use ($openVpnReader) {
            $userList = $openVpnReader->v('Users');
            if (!array_key_exists($userId, $userList)) {
                return false;
            }

            return $userList[$userId];
        },
        array('realm' => 'VPN Server API')
    );

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->addModule(new LogModule($connectionLog));
    $service->addModule(new OpenVpnModule($serverManager, $logger));
    $service->addModule(new ConfigModule($staticConfig, $logger));
    $service->addModule(new CaModule($crlFetcher, $logger));
    $service->run()->send();
} catch (Exception $e) {
    // internal server error
    syslog(LOG_ERR, $e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getJsonResponse()->send();
}
