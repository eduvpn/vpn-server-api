<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server;

use fkooman\OAuth\Client\Exception\SessionException;
use fkooman\OAuth\Client\SessionInterface;

class NullSession implements SessionInterface
{
    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        throw new SessionException('not implemented');
    }

    /**
     * Get value, delete key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function take($key)
    {
        throw new SessionException('not implemented');
    }
}
