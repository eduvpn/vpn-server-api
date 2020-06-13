<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Tests\Api;

use DateTime;
use LC\Server\Api\ConnectionsModule;

class TestConnectionsModule extends ConnectionsModule
{
    public function setDateTime(DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
    }
}
