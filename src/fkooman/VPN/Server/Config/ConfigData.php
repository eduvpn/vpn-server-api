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

use fkooman\VPN\Server\InputValidation;

class ConfigData
{
    /** @var string */
    private $pool;

    /** @var bool */
    private $disable;

    public function __construct(array $configData)
    {
        $pool = array_key_exists('pool', $configData) ? $configData['pool'] : 'default';
        InputValidation::pool($pool);
        $disable = array_key_exists('disable', $configData) ? $configData['disable'] : false;
        InputValidation::disable($disable);

        $this->pool = $pool;
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
