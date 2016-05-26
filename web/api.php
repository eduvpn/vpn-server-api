<?php

/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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
use fkooman\Rest\Plugin\Authentication\Bearer\ArrayBearerValidator;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\Rest\Service;
use fkooman\VPN\Server\Api\CaModule;
use fkooman\VPN\Server\Api\CommonNamesModule;
use fkooman\VPN\Server\Api\InfoModule;
use fkooman\VPN\Server\Api\LogModule;
use fkooman\VPN\Server\Api\OpenVpnModule;
use fkooman\VPN\Server\Api\UsersModule;
use fkooman\VPN\Server\ConnectionLog;
use fkooman\VPN\Server\CrlFetcher;
use fkooman\VPN\Server\Disable;
use fkooman\VPN\Server\OpenVpn\ManagementSocket;
use fkooman\VPN\Server\OpenVpn\ServerManager;
use fkooman\VPN\Server\OtpSecret;
use fkooman\VPN\Server\Pools;
use GuzzleHttp\Client;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;


try {
    $config = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    $poolsConfig = new Reader(
        new YamlFile(dirname(__DIR__).'/config/pools.yaml')
    );

    $aclConfig = new Reader(
        new YamlFile(dirname(__DIR__).'/config/acl.yaml')
    );

    $serverPools = new Pools($poolsConfig->v('pools'));

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

    $logger = new Logger('vpn-server-api');
    $syslog = new SyslogHandler('vpn-server-api', 'user');
    $formatter = new LineFormatter();
    $syslog->setFormatter($formatter);
    $logger->pushHandler($syslog);

    $managementSocket = new ManagementSocket();

    // handles the connection to the various OpenVPN instances
    $serverManager = new ServerManager($serverPools, $managementSocket, $logger);

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

    // http request router
    $service = new Service();

    // API authentication
    $apiAuth = new BearerAuthentication(
        new ArrayBearerValidator(
            $config->v('api')
        ),
        ['realm' => 'VPN Server API']
     );

    // ACL
    $aclMethod = $aclConfig->v('aclMethod');
    $aclClass = sprintf('fkooman\VPN\Server\Acl\%s', $aclMethod);
    $acl = new $aclClass($aclConfig);

    $usersDisable = new Disable($poolsConfig->v('configDir').'/users/disabled');
    $commonNamesDisable = new Disable($poolsConfig->v('configDir').'/common_names/disabled');
    $otpSecrets = new OtpSecret($poolsConfig->v('configDir').'/users/otp_secrets');

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($apiAuth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->addModule(new LogModule($connectionLog));
    $service->addModule(new OpenVpnModule($serverManager));
    $service->addModule(new CommonNamesModule($commonNamesDisable, $logger));
    $service->addModule(new UsersModule($usersDisable, $otpSecrets, $logger));
    $service->addModule(new CaModule($crlFetcher, $logger));
    $service->addModule(new InfoModule($serverPools, $acl));
    $service->run()->send();
} catch (Exception $e) {
    // internal server error
    syslog(LOG_ERR, $e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getJsonResponse()->send();
}
