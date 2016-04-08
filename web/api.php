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
use fkooman\Rest\Service;
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\VPN\Server\Ca\CaModule;
use fkooman\VPN\Server\Ca\CrlFetcher;
use fkooman\VPN\Server\Config\ConfigModule;
use fkooman\VPN\Server\Config\FileConfigStorage;
use fkooman\VPN\Server\Info\InfoModule;
use fkooman\VPN\Server\Log\ConnectionLog;
use fkooman\VPN\Server\Log\LogModule;
use fkooman\VPN\Server\OpenVpn\OpenVpnModule;
use fkooman\VPN\Server\OpenVpn\ServerApi;
use fkooman\VPN\Server\OpenVpn\ServerManager;
use fkooman\VPN\Server\OpenVpn\ServerSocket;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use GuzzleHttp\Client;

try {
    $config = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    $ipConfig = new Reader(
        new YamlFile(dirname(__DIR__).'/config/ip.yaml')
    );

    $logConfig = new Reader(
        new YamlFile(dirname(__DIR__).'/config/log.yaml')
    );

    $client = new Client(
        [
            'defaults' => [
                'headers' => [
                    'Authorization' => sprintf(
                        'Bearer %s',
                        $config->v(
                            'remoteApi',
                            'vpn-ca-api',
                            'token'
                        )
                    ),
                ],
            ],
        ]
    );

    // handles fetching the certificate revocation list
    $crlFetcher = new CrlFetcher(
        sprintf('%s/ca.crl', $config->v('remoteApi', 'vpn-ca-api', 'uri')),
        $config->v('crl', 'path'),
        $client
    );

    // handles the client configuration directory
    $staticConfig = new FileConfigStorage(
        $ipConfig->v('configDir')
    );

    // handles the connection to the various OpenVPN instances
    $serverManager = new ServerManager();
    foreach ($config->v('openVpn') as $k => $v) {
        $socket = sprintf('tcp://localhost:%d', $v['managementPort']);
        $serverManager->addServer(
            new ServerApi(
                $k,
                new ServerSocket($socket)
            )
        );
    }

    // handles the connection history log
    try {
        $db = new PDO(
            $logConfig->v('log', 'dsn'),
            $logConfig->v('log', 'username', false),
            $logConfig->v('log', 'password', false)
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

    // API authentication
    $apiAuth = new BearerAuthentication(
        new ArrayBearerValidator(
            $config->v('api')
        ),
        ['realm' => 'VPN Server API']
     );

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->addModule(new LogModule($connectionLog));
    $service->addModule(new OpenVpnModule($serverManager, $logger));
    $service->addModule(new ConfigModule($staticConfig, $logger));
    $service->addModule(new CaModule($crlFetcher, $logger));
    $service->addModule(new InfoModule($ipConfig->v('v4'), $ipConfig->v('v6')));
    $service->run()->send();
} catch (Exception $e) {
    // internal server error
    syslog(LOG_ERR, $e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getJsonResponse()->send();
}
