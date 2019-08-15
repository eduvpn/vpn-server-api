<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Api;

use LC\Common\FileIO;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use RuntimeException;

class StatsModule implements ServiceModuleInterface
{
    /** @var string */
    private $dataDir;

    /**
     * @param string $dataDir
     */
    public function __construct($dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->get(
            '/stats',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-user-portal']);
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
