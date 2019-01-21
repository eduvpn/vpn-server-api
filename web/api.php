<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\OpenVpn\ManagementSocket;
use LetsConnect\Common\Config;
use LetsConnect\Common\Http\BasicAuthenticationHook;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Response;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\Json;
use LetsConnect\Common\Logger;
use LetsConnect\Common\Random;
use LetsConnect\Server\Api\CertificatesModule;
use LetsConnect\Server\Api\ConnectionsModule;
use LetsConnect\Server\Api\InfoModule;
use LetsConnect\Server\Api\LogModule;
use LetsConnect\Server\Api\OpenVpnModule;
use LetsConnect\Server\Api\StatsModule;
use LetsConnect\Server\Api\SystemMessagesModule;
use LetsConnect\Server\Api\UserMessagesModule;
use LetsConnect\Server\Api\UsersModule;
use LetsConnect\Server\CA\EasyRsaCa;
use LetsConnect\Server\OpenVpn\ServerManager;
use LetsConnect\Server\Storage;
use LetsConnect\Server\TlsAuth;

$logger = new Logger('vpn-server-api');

try {
    // this is provided by Apache, using CanonicalName
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    $configDir = sprintf('%s/config', $baseDir);

    $config = Config::fromFile(
        sprintf('%s/config.php', $configDir)
    );

    $service = new Service();
    $basicAuthentication = new BasicAuthenticationHook(
        $config->getSection('apiConsumers')->toArray(),
        'vpn-server-backend'
    );
    $service->addBeforeHook('auth', $basicAuthentication);

    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir),
        new DateTime('now')
    );
    $storage->update();

    $service->addModule(
        new ConnectionsModule(
            $config,
            $storage
        )
    );

    $service->addModule(
        new StatsModule(
            $dataDir
        )
    );

    $service->addModule(
        new UsersModule(
            $config,
            $storage
        )
    );

    $service->addModule(
        new InfoModule(
            $config
        )
    );

    $service->addModule(
        new OpenVpnModule(
            new ServerManager($config, $logger, new ManagementSocket()),
            $storage
        )
    );

    $service->addModule(
        new LogModule(
            $storage
        )
    );

    $service->addModule(
        new SystemMessagesModule(
            $storage
        )
    );

    $service->addModule(
        new UserMessagesModule(
            $storage
        )
    );

    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);

    $easyRsaCa = new EasyRsaCa(
        $easyRsaDir,
        $easyRsaDataDir
    );
    $tlsAuth = new TlsAuth($dataDir);

    $service->addModule(
        new CertificatesModule(
            $easyRsaCa,
            $storage,
            $tlsAuth,
            new Random()
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new Response(500, 'application/json');
    $response->setBody(Json::encode(['error' => $e->getMessage()]));
    $response->send();
}
