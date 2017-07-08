<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use fkooman\OAuth\Client\Http\CurlHttpClient;
use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Common\Random;
use SURFnet\VPN\Server\Acl\Provider\StaticProvider;
use SURFnet\VPN\Server\Acl\Provider\VootProvider;
use SURFnet\VPN\Server\Api\CertificatesModule;
use SURFnet\VPN\Server\Api\ConnectionsModule;
use SURFnet\VPN\Server\Api\InfoModule;
use SURFnet\VPN\Server\Api\LogModule;
use SURFnet\VPN\Server\Api\OpenVpnModule;
use SURFnet\VPN\Server\Api\StatsModule;
use SURFnet\VPN\Server\Api\SystemMessagesModule;
use SURFnet\VPN\Server\Api\UserMessagesModule;
use SURFnet\VPN\Server\Api\UsersModule;
use SURFnet\VPN\Server\CA\EasyRsaCa;
use SURFnet\VPN\Server\NullSession;
use SURFnet\VPN\Server\OpenVpn\ManagementSocket;
use SURFnet\VPN\Server\OpenVpn\ServerManager;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\TlsAuth;

$logger = new Logger('vpn-server-api');

try {
    // this is provided by Apache, using CanonicalName
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $instanceId = getenv('VPN_INSTANCE_ID')) {
        $instanceId = $request->getServerName();
    }

    $dataDir = sprintf('%s/data/%s', dirname(__DIR__), $instanceId);
    $configDir = sprintf('%s/config/%s', dirname(__DIR__), $instanceId);

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
        new DateTime('now')
    );

    $groupProviders = [];
    if ($config->hasSection('groupProviders')) {
        $enabledProviders = array_keys($config->getSection('groupProviders')->toArray());
        // StaticProvider
        if (in_array('StaticProvider', $enabledProviders)) {
            $groupProviders[] = new StaticProvider(
                $config->getSection('groupProviders')->getSection('StaticProvider')
            );
        }
        // VootProvider
        if (in_array('VootProvider', $enabledProviders)) {
            $provider = new Provider(
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('clientId'),
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('clientSecret'),
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('authorizationEndpoint'),
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('tokenEndpoint')
            );
            $oauthClient = new OAuthClient(
                $storage,
                new CurlHttpClient(),
                new NullSession()
            );
            $oauthClient->setProvider($provider);
            $groupProviders[] = new VootProvider(
                $oauthClient,
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('apiUrl')
            );
        }
    }

    $service->addModule(
        new ConnectionsModule(
            $config,
            $storage,
            $groupProviders
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
            $storage,
            $groupProviders
        )
    );

    $service->addModule(
        new InfoModule(
            $config
        )
    );

    $service->addModule(
        new OpenVpnModule(
            new ServerManager($config, new ManagementSocket(), $logger),
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

    $easyRsaDir = sprintf('%s/easy-rsa', dirname(__DIR__));
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
    $response->setBody(json_encode(['error' => $e->getMessage()]));
    $response->send();
}
