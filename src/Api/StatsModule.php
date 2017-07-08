<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Api;

use RuntimeException;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\ApiResponse;
use SURFnet\VPN\Common\Http\AuthUtils;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Http\ServiceModuleInterface;

class StatsModule implements ServiceModuleInterface
{
    /** @var string */
    private $dataDir;

    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    public function init(Service $service)
    {
        $service->get(
            '/stats',
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-admin-portal']);
                $statsFile = sprintf('%s/stats.json', $this->dataDir);

                try {
                    return new ApiResponse('stats', FileIO::readJsonFile($statsFile));
                } catch (RuntimeException $e) {
                    // no stats file available yet
                    return new ApiResponse('stats', false);
                }
            }
        );
    }
}
