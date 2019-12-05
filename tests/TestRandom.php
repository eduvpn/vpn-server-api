<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Tests;

use LC\Common\RandomInterface;
use RuntimeException;

class TestRandom implements RandomInterface
{
    /** @var array */
    private $randomData;

    /** @var int */
    private $randomCount = 0;

    /**
     * @param array<string> $randomData
     */
    public function __construct(array $randomData)
    {
        $this->randomData = $randomData;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function get($length)
    {
        if (!\array_key_exists($this->randomCount, $this->randomData)) {
            throw new RuntimeException('no more "random" data');
        }

        return $this->randomData[$this->randomCount++];
    }
}
