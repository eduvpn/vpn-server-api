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

class IPv6
{
    /** @var string */
    private $prefix;

    /** @var array */
    private $dns;

    public function __construct(array $input)
    {
        $this->parseConfig($input);
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function getDns()
    {
        return $this->dns;
    }

    /**
     * Parse and validate the configuration and set default values if they
     * are missing from the configuration file.
     */
    private function parseConfig(array $input)
    {
        foreach (['prefix'] as $k) {
            if (!array_key_exists($k, $input)) {
                throw new RuntimeException(sprintf('missing key "%s" in configuration', $k));
            }
        }
        $this->prefix = $input['prefix'];

        if (!array_key_exists('dns', $input)) {
            $input['dns'] = ['2001:4860:4860::8888', '2001:4860:4860::8844'];
        }
        $this->dns = $input['dns'];
    }
}
