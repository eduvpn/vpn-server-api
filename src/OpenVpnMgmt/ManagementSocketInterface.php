<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\OpenVpnMgmt;

interface ManagementSocketInterface
{
    /**
     * @param string $socketAddress
     * @param int    $timeOut
     *
     * @return void
     */
    public function open($socketAddress, $timeOut = 5);

    /**
     * @param string $command
     *
     * @return array<int, string>
     */
    public function command($command);

    /**
     * @return void
     */
    public function close();
}
