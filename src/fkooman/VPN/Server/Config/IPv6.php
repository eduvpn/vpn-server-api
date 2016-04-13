<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\Server\Config;

use InvalidArgumentException;

class IPv6
{
    /** @var string */
    private $ip;

    /** @var int */
    private $prefix;

    public function __construct($prefixIp)
    {
        // must be of form IP/PREFIX
        if (1 !== substr_count($prefixIp, '/')) {
            throw new InvalidArgumentException('not a prefix');
        }
        list($ip, $prefix) = explode('/', $prefixIp);

        // check IP
        self::validateIP($ip);
        $this->ip = inet_ntop(inet_pton($ip));

        // check prefix
        if (!is_numeric($prefix) || 0 > $prefix || 64 < $prefix) {
            throw new InvalidArgumentException('invalid prefix, must be <= /64');
        }

        $this->prefix = intval($prefix);
    }

    public function getRange()
    {
        return sprintf('%s/%d', $this->ip, $this->prefix);
    }

    /**
     * Split the provided range in $no /64s.
     */
    public function splitRange($no)
    {
        // XXX must be at least /60

        $bitIp = inet_pton($this->ip);
        $hexIp = bin2hex($bitIp);
        $splitIp = str_split($hexIp, 4);

        $ranges = [];

        for ($i = 0; $i < $no; ++$i) {
            // the last 4 fields become 0, the last digit of field 3 becomes 0, and 1, ...

            $splitIp[3] = dechex(
                (
                    hexdec($splitIp[3]) & 0xfff0
                ) + $i
            );

            $splitIp[4] = 0;
            $splitIp[5] = 0;
            $splitIp[6] = 0;
            $splitIp[7] = 0;
            $rangeIp = inet_ntop(inet_pton(implode($splitIp, ':')));

            $ranges[] = $rangeIp.'/64';
        }

        return $ranges;
    }

    private static function validateIP($ip)
    {
        if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new InvalidArgumentException('invalid IP address');
        }
    }
}
