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

class PoolConfig
{
    /** @var string */
    private $name;

    /** @var IP */
    private $range;

    /** @var array */
    private $dstNet4;

    /** @var array */
    private $dstNet6;

    /** @var array */
    private $dstPort;

    public function __construct(array $c)
    {
        if (!array_key_exists('name', $c)) {
            throw new InvalidArgumentException('missing "name"');
        }
        $this->name = $c['name'];

        if (!array_key_exists('range', $c)) {
            throw new InvalidArgumentException('missing "range"');
        }
        // XXX validate range
        $this->range = new IP($c['range']);

        // default values
        if (!array_key_exists('firewall', $c)) {
            $c['firewall'] = [];
        }
        if (!array_key_exists('dst_net', $c['firewall'])) {
            $c['firewall']['dst_net'] = ['0.0.0.0/0', '::/0'];
        }
        if (!array_key_exists('dst_port', $c['firewall'])) {
            $c['firewall']['dst_port'] = [];
        }

        $this->dstNet4 = [];
        $this->dstNet6 = [];

        foreach ($c['firewall']['dst_net'] as $dstNet) {
            // XXX validate the nets
            if (false !== strpos($dstNet, ':')) {
                $this->dstNet6[] = $dstNet;
            } else {
                $this->dstNet4[] = $dstNet;
            }
        }

        $this->dstPort = $c['firewall']['dst_port'];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getRange()
    {
        return $this->range;
    }

    public function getDstNet4()
    {
        return $this->dstNet4;
    }

    public function getDstNet6()
    {
        return $this->dstNet6;
    }

    public function getDstPort()
    {
        return $this->dstPort;
    }

    public function useDefaultGateway()
    {
        return in_array('0.0.0.0/0', $this->dstNet4) || in_array('::/0', $this->dstNet6);
    }
}
