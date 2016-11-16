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

use SURFnet\VPN\Common\Http\ServiceModuleInterface;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\FileIO;
use RuntimeException;

class MotdModule implements ServiceModuleInterface
{
    /** @var string */
    private $motdFile;

    public function __construct($dataDir)
    {
        $this->motdFile = sprintf('%s/motd', $dataDir);
    }

    public function init(Service $service)
    {
        $service->get(
            '/get_motd',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-admin-portal', 'vpn-user-portal']);

                try {
                    return new ApiResponse('get_motd', FileIO::readFile($this->motdFile));
                } catch (RuntimeException $e) {
                    // no motd
                    return new ApiResponse('get_motd', false);
                }
            }
        );

        $service->post(
            '/set_motd',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-admin-portal']);

                $motdMessage = $request->getPostParameter('motd_message');

                // sanitize the motdMessage
                $motdMessage = htmlspecialchars($motdMessage, ENT_QUOTES);

                try {
                    FileIO::writeFile($this->motdFile, $motdMessage);

                    return new ApiResponse('set_motd', true);
                } catch (RuntimeException $e) {
                    return new ApiResponse('set_motd', false);
                }
            }
        );

        $service->post(
            '/delete_motd',
            function (Request $request, array $hookData) {
                Utils::requireUser($hookData, ['vpn-admin-portal']);

                try {
                    FileIO::deleteFile($this->motdFile);

                    return new ApiResponse('delete_motd', true);
                } catch (RuntimeException $e) {
                    return new ApiResponse('delete_motd', false);
                }
            }
        );
    }
}
