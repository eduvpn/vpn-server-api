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
use fkooman\Rest\Service;
use fkooman\VPN\Server\Manage;
use fkooman\Http\Request;
use fkooman\Http\JsonResponse;
use fkooman\VPN\Server\CrlFetcher;

try {
    $reader = new Reader(
        new YamlFile(dirname(__DIR__).'/config/config.yaml')
    );

    $manage = new Manage($reader->v('OpenVpn'));

    $crlFetcher = new CrlFetcher(
        $reader->v('Crl', 'url'),
        $reader->v('Crl', 'path')
    );

    $service = new Service();
    $service->get(
        '/connections',
        function (Request $request) use ($manage) {
            $clientInfo = $manage->getConnections();
            $response = new JsonResponse();
            $response->setBody($clientInfo);

            return $response;
        }
    );

    $service->get(
        '/servers',
        function (Request $request) use ($manage) {
            $serverInfo = $manage->getServerInfo();
            $response = new JsonResponse();
            $response->setBody($serverInfo);

            return $response;
        }
    );

    $service->post(
        '/kill',
        function (Request $request) use ($manage) {
            // XXX: should we disconnect the user from all servers?
            $id = $request->getPostParameter('id');
            $commonName = $request->getPostParameter('common_name');

            $response = new JsonResponse();
            $response->setBody(
                array(
                    'ok' => $manage->killClient($id, $commonName),
                )
            );

            return $response;
        }
    );

    $service->post(
        '/refreshCrl',
        function (Request $request) use ($crlFetcher) {
            $crlFetcher->fetch();

            return new JsonResponse();
        }
    );

    $auth = new BasicAuthentication(
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
    $authenticationPlugin->register($auth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->run()->send();
} catch (Exception $e) {
    error_log($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
