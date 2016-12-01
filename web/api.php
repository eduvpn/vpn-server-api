<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\BasicAuthenticationHook;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Response;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Common\Random;
use SURFnet\VPN\Server\Api\CertificatesModule;
use SURFnet\VPN\Server\Api\ConnectionsModule;
use SURFnet\VPN\Server\Api\InfoModule;
use SURFnet\VPN\Server\Api\LogModule;
use SURFnet\VPN\Server\Api\MotdModule;
use SURFnet\VPN\Server\Api\OpenVpnModule;
use SURFnet\VPN\Server\Api\StatsModule;
use SURFnet\VPN\Server\Api\UsersModule;
use SURFnet\VPN\Server\CA\EasyRsaCa;
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
        sprintf('%s/config.yaml', $configDir)
    );

    $service = new Service();
    $basicAuthentication = new BasicAuthenticationHook(
        $config->v('apiConsumers'),
        'vpn-server-backend'
    );
    $service->addBeforeHook('auth', $basicAuthentication);

    $random = new Random();

    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        $random
    );

    $groupProviders = [];
    if ($config->e('groupProviders')) {
        foreach (array_keys($config->v('groupProviders')) as $groupProviderId) {
            $groupProviderClass = sprintf('SURFnet\VPN\Server\Acl\Provider\%s', $groupProviderId);
            $groupProviders[] = new $groupProviderClass(
                new Config(
                    $config->v('groupProviders', $groupProviderId)
                ),
                $dataDir
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
        new MotdModule(
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
            $random
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new Response(500, 'application/json');
    $response->setBody(json_encode(['error' => $e->getMessage()]));
    $response->send();
}
