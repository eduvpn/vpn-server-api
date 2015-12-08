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
use fkooman\Ini\IniReader;
use fkooman\Rest\Service;
use fkooman\VPN\Manage;
use fkooman\Http\Request;
use fkooman\Http\JsonResponse;

try {
    $iniReader = IniReader::fromFile(
        dirname(__DIR__).'/config/config.ini'
    );

    $manage = new Manage($iniReader->v('socket'));

    $service = new Service();
    $service->get(
        '/status',
        function (Request $request) use ($manage) {
            $clientInfo = $manage->getClientInfo();
            $response = new JsonResponse();
            $response->setBody($clientInfo);

            return $response;
        }
    );

    $service->post(
        '/disconnect',
        function (Request $request) use ($manage) {
            $configId = $request->getPostParameter('config_id');
            $manage->killClient($configId);

            return new JsonResponse();
        }
    );

    $auth = new BasicAuthentication(
        function ($userId) use ($iniReader) {
            $userList = $iniReader->v('BasicAuthentication');
            if (!array_key_exists($userId, $userList)) {
                return false;
            }

            return $userList[$userId];
        },
        array('realm' => 'OpenVPN Management API')
    );

    $authenticationPlugin = new AuthenticationPlugin();
    $authenticationPlugin->register($auth, 'api');
    $service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    $service->run()->send();
} catch (Exception $e) {
    error_log($e->getMessage());
    die(sprintf('ERROR: %s', $e->getMessage()));
}
