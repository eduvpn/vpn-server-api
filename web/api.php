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

use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use fkooman\Rest\Plugin\Authentication\Basic\BasicAuthentication;
use fkooman\Config\Reader;
use fkooman\Config\YamlFile;
use fkooman\VPN\Server\ServerService;
use fkooman\VPN\Server\ServerManager;
use fkooman\VPN\Server\ServerApi;
use fkooman\VPN\Server\ServerSocket;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\VPN\Server\CrlFetcher;
use fkooman\VPN\Server\SimpleError;
use fkooman\VPN\Server\CcdHandler;

SimpleError::register();

try {
    $reader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    // handles fetching the certificate revocation list
    $crlFetcher = new CrlFetcher(
        $reader->v('Crl', 'url'),
        $reader->v('Crl', 'path')
    );

    // handles the client configuration directory
    $ccdHandler = new CcdHandler(
        $reader->v('Ccd', 'path')
    );

    // handles the connection to the various OpenVPN instances
    $serverManager = new ServerManager();
    foreach ($reader->v('OpenVpn') as $openVpnServer) {
        $serverManager->addServer(
            new ServerApi(
                $openVpnServer['id'],
                new ServerSocket($openVpnServer['socket'])
            )
        );
    }

    // http request router
    $service = new ServerService($serverManager, $ccdHandler, $crlFetcher);

    $apiAuth = new BasicAuthentication(
        function ($userId) use ($reader) {
            $userList = $reader->v('Users');
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
    $service->run()->send();
} catch (Exception $e) {
    // internal server error
    error_log($e->__toString());
    $e = new InternalServerErrorException($e->getMessage());
    $e->getJsonResponse()->send();
}
