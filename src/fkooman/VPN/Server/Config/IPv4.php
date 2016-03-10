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

class IPv4
{
    /** @var IP */
    private $range;

    /** @var array */
    private $dns;

    /** @var array */
    private $pools;

    public function __construct(array $input)
    {
        $this->parseConfig($input);
    }

    public function getRange()
    {
        return $this->range;
    }

    public function getDns()
    {
        return $this->dns;
    }

    public function getPool($id)
    {
        return $this->pools[$id];
    }

    public function getPools()
    {
        return $this->pools;
    }

    /**
     * Parse and validate the configuration and set default values if they
     * are missing from the configuration file.
     */
    private function parseConfig(array $input)
    {
        foreach (['range', 'pools'] as $k) {
            if (!array_key_exists($k, $input)) {
                throw new RuntimeException(sprintf('missing key "%s" in configuration', $k));
            }
        }

        $this->range = new IP($input['range']);

        if (!array_key_exists('dns', $input)) {
            $input['dns'] = ['8.8.8.8', '8.8.4.4'];
        }
        $this->dns = $input['dns'];

        $this->pools = [];
        foreach ($input['pools'] as $k => $v) {
            $this->pools[$k] = new PoolConfig($v);
        }
    }
}
