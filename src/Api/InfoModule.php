<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Api;

use DateTime;
use DateTimeZone;
use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\ProfileConfig;

class InfoModule implements ServiceModuleInterface
{
    /** @var \LC\Common\Config */
    private $config;

    /** @var string */
    private $caDir;

    /**
     * @param string $caDir
     */
    public function __construct(Config $config, $caDir)
    {
        $this->config = $config;
        $this->caDir = $caDir;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        /* INFO */
        $service->get(
            '/profile_list',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-server-node']);

                $profileList = [];
                foreach ($this->config->requireArray('vpnProfiles') as $profileId => $profileData) {
                    $profileConfig = new ProfileConfig(new Config($profileData));
                    $profileList[$profileId] = $profileConfig->toArray();
                }

                return new ApiResponse('profile_list', $profileList);
            }
        );

        /* INFO */
        $service->get(
            '/ca_info',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $certData = trim(FileIO::readFile($this->caDir.'/ca.crt'));
                $parsedCert = openssl_x509_parse($certData);

                $validFrom = new DateTime('@'.$parsedCert['validFrom_time_t']);
                $validFrom->setTimezone(new DateTimeZone('UTC'));
                $validTo = new DateTime('@'.$parsedCert['validTo_time_t']);
                $validTo->setTimezone(new DateTimeZone('UTC'));

                return new ApiResponse(
                    'ca_info',
                    [
                        'valid_from' => $validFrom->format(DateTime::ATOM),
                        'valid_to' => $validTo->format(DateTime::ATOM),
                        'ca_key_type' => $this->config->requireString('vpnCaKeyType', 'ECDSA'),
                    ]
                );

                return new ApiResponse('ca_info', $caInfo);
            }
        );
    }
}
