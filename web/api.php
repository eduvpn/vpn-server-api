<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\Config;
use LC\Common\Http\BasicAuthenticationHook;
use LC\Common\Http\Request;
use LC\Common\Http\Response;
use LC\Common\Http\Service;
use LC\Common\Json;
use LC\Common\Logger;
use LC\Common\Random;
use LC\OpenVpn\ManagementSocket;
use LC\Server\Api\CertificatesModule;
use LC\Server\Api\ConnectionsModule;
use LC\Server\Api\InfoModule;
use LC\Server\Api\LogModule;
use LC\Server\Api\OpenVpnDaemonModule;
use LC\Server\Api\OpenVpnModule;
use LC\Server\Api\StatsModule;
use LC\Server\Api\SystemMessagesModule;
use LC\Server\Api\UserMessagesModule;
use LC\Server\Api\UsersModule;
use LC\Server\CA\VpnCa;
use LC\Server\OpenVpn\DaemonSocket;
use LC\Server\OpenVpn\ServerManager;
use LC\Server\Storage;
use LC\Server\TlsCrypt;

$logger = new Logger('vpn-server-api');

try {
    // this is provided by Apache, using CanonicalName
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    $configDir = sprintf('%s/config', $baseDir);
    $vpnCaDir = sprintf('%s/ca', $dataDir);

    $config = Config::fromFile(
        sprintf('%s/config.php', $configDir)
    );

    $service = new Service();
    $basicAuthentication = new BasicAuthenticationHook(
        $config->requireArray('apiConsumers'),
        'vpn-server-backend'
    );
    $service->addBeforeHook('auth', $basicAuthentication);

    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );
    $storage->update();

    $service->addModule(
        new ConnectionsModule(
            $config,
            $storage,
            $logger
        )
    );

    $service->addModule(
        new StatsModule(
            $dataDir,
            $storage
        )
    );

    $service->addModule(
        new UsersModule(
            $storage
        )
    );

    $service->addModule(
        new InfoModule(
            $config,
            $vpnCaDir
        )
    );

    if ($config->requireBool('useVpnDaemon', false)) {
        $openVpnDaemonModule = new OpenVpnDaemonModule(
            $config,
            $storage,
            new DaemonSocket(sprintf('%s/vpn-daemon', $configDir), $config->requireBool('vpnDaemonTls', true))
        );
        $openVpnDaemonModule->setLogger($logger);
        $service->addModule($openVpnDaemonModule);
    } else {
        $service->addModule(
            new OpenVpnModule(
                new ServerManager($config, $logger, new ManagementSocket()),
                $storage
            )
        );
    }

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

    // we need easyRsaDataDir for migrations to vpn-ca
    $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);
    $vpnCaPath = $config->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    $vpnCaKeyType = $config->requireString('vpnCaKeyType', 'RSA');
    // VpnCa gets the easyRsaDataDir in case a migration is needed...
    $ca = new VpnCa($vpnCaDir, $vpnCaKeyType, $vpnCaPath, $easyRsaDataDir);

    $service->addModule(
        new CertificatesModule(
            $config,
            $ca,
            $storage,
            new TlsCrypt($dataDir),
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
