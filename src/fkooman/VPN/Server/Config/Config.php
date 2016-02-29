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

use fkooman\Http\Exception\BadRequestException;

class Config
{
    /** @var string */
    private $pool;

    /** @var bool */
    private $disable;

    public function __construct(array $config)
    {
        // pool
        $pool = 'default';
        if (array_key_exists('pool', $config)) {
            // XXX consider mb_string
            if (!is_string($config['pool']) || 1 > strlen($config['pool'])) {
                throw new BadRequestException('"pool" must be non-empty string');
            }
            $pool = $config['pool'];
        }
        $this->pool = $pool;

        // disable
        $disable = false;
        if (array_key_exists('disable', $config)) {
            if (!is_bool($config['disable'])) {
                throw new BadRequestException('"disable" must be boolean');
            }
            $disable = $config['disable'];
        }
        $this->disable = $disable;
    }

    public function getPool()
    {
        return $this->pool;
    }

    public function getDisable()
    {
        return $this->disable;
    }

    public function toArray()
    {
        return [
            'pool' => $this->pool,
            'disable' => $this->disable,
        ];
    }
}
