<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Client\Http\CurlHttpClient;
use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use LC\OpenVpn\ManagementSocket;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Json;
use SURFnet\VPN\Common\LdapClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Common\Random;
use SURFnet\VPN\Server\Acl\Provider\LdapProvider;
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

    $dataDir = sprintf('%s/data/%s', $baseDir, $instanceId);
    $configDir = sprintf('%s/config/%s', $baseDir, $instanceId);

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

    $groupProviders = [];
    if ($config->hasSection('groupProviders')) {
        $enabledProviders = array_keys($config->getSection('groupProviders')->toArray());
        // StaticProvider
        if (in_array('StaticProvider', $enabledProviders, true)) {
            $groupProviders[] = new StaticProvider(
                $config->getSection('groupProviders')->getSection('StaticProvider')
            );
        }
        // VootProvider
        if (in_array('VootProvider', $enabledProviders, true)) {
            $provider = new Provider(
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('clientId'),
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('clientSecret'),
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('authorizationEndpoint'),
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('tokenEndpoint')
            );
            $oauthClient = new OAuthClient(
                $storage,
                new CurlHttpClient([], $logger)
            );
            $oauthClient->setSession(new NullSession());
            $groupProviders[] = new VootProvider(
                $oauthClient,
                $provider,
                $config->getSection('groupProviders')->getSection('VootProvider')->getItem('apiUrl')
            );
        }
        if (in_array('LdapProvider', $enabledProviders, true)) {
            $ldapConfig = $config->getSection('groupProviders')->getSection('LdapProvider');
            $ldapClient = new LdapClient($ldapConfig->getItem('ldapUri'));

            // backwards compatible support for old "groupDn" and "filterTemplate". remove in 2.0
            $groupBaseDn = $ldapConfig->hasItem('groupDn') ? $ldapConfig->getItem('groupDn') : $ldapConfig->getItem('groupBaseDn');
            $memberFilterTemplate = $ldapConfig->hasItem('filterTemplate') ? $ldapConfig->getItem('filterTemplate') : $ldapConfig->getItem('memberFilterTemplate');

            $groupProviders[] = new LdapProvider(
                $logger,
                $ldapClient,
                $groupBaseDn,
                $memberFilterTemplate,
                $ldapConfig->hasItem('userBaseDn') ? $ldapConfig->getItem('userBaseDn') : null,
                $ldapConfig->hasItem('userIdFilterTemplate') ? $ldapConfig->getItem('userIdFilterTemplate') : null,
                $ldapConfig->hasItem('bindDn') ? $ldapConfig->getItem('bindDn') : null,
                $ldapConfig->hasItem('bindPass') ? $ldapConfig->getItem('bindPass') : null
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
        $config,
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
