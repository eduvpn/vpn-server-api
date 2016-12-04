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

namespace SURFnet\VPN\Server\Api;

use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\RandomInterface;
use SURFnet\VPN\Server\CA\CaInterface;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\TlsAuth;

class CertificatesModule implements ServiceModuleInterface
{
    /** @var \SURFnet\VPN\Server\CA\CaInterface */
    private $ca;

    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    /** @var \SURFnet\VPN\Server\TlsAuth */
    private $tlsAuth;

    /** @var \SURFnet\VPN\Common\RandomInterface */
    private $random;

    public function __construct(CaInterface $ca, Storage $storage, TlsAuth $tlsAuth, RandomInterface $random)
    {
        $this->ca = $ca;
        $this->storage = $storage;
        $this->tlsAuth = $tlsAuth;
        $this->random = $random;
    }

    public function init(Service $service)
    {
        /* CERTIFICATES */
        $service->post(
            '/add_client_certificate',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);

                $userId = InputValidation::userId($request->getPostParameter('user_id'));
                $displayName = InputValidation::displayName($request->getPostParameter('display_name'));

                // generate a random string as the certificate's CN
                $commonName = $this->random->get(16);
                $certInfo = $this->ca->clientCert($commonName);
                // add TLS Auth
                $certInfo['ta'] = $this->tlsAuth->get();
                $certInfo['ca'] = $this->ca->caCert();

                $this->storage->addCertificate($userId, $commonName, $displayName, $certInfo['valid_from'], $certInfo['valid_to']);

                return new ApiResponse('add_client_certificate', $certInfo, 201);
            }
        );

        $service->post(
            '/add_server_certificate',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                $commonName = InputValidation::serverCommonName($request->getPostParameter('common_name'));

                $certInfo = $this->ca->serverCert($commonName);
                // add TLS Auth
                $certInfo['ta'] = $this->tlsAuth->get();
                $certInfo['ca'] = $this->ca->caCert();

                return new ApiResponse('add_server_certificate', $certInfo, 201);
            }
        );

        $service->post(
            '/disable_client_certificate',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $commonName = InputValidation::commonName($request->getPostParameter('common_name'));

                return new ApiResponse('disable_client_certificate', $this->storage->disableCertificate($commonName));
            }
        );

        $service->post(
            '/enable_client_certificate',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);

                $commonName = InputValidation::commonName($request->getPostParameter('common_name'));

                return new ApiResponse('enable_client_certificate', $this->storage->enableCertificate($commonName));
            }
        );

        $service->get(
            '/client_certificate_list',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $userId = InputValidation::userId($request->getQueryParameter('user_id'));

                return new ApiResponse('client_certificate_list', $this->storage->getCertificates($userId));
            }
        );

        $service->get(
            '/client_certificate_info',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal', 'vpn-admin-portal']);

                $commonName = InputValidation::commonName($request->getQueryParameter('common_name'));

                return new ApiResponse('client_certificate_info', $this->storage->getUserCertificateInfo($commonName));
            }
        );
    }
}
