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

use RuntimeException;
use fkooman\VPN\Server\Utils;

/**
 * This class generates a configuration file specific for the connecting
 * client CN.
 */
class ClientConfig
{
    /** @var string */
    private $leaseDir;

    /** @var ConfigStorageInterface */
    private $configStorage;

    /** @var IPv4 */
    private $v4;

    /** @var IPv6 */
    private $v6;

    public function __construct(array $input, ConfigStorageInterface $configStorage)
    {
        $this->parseConfig($input);
        $this->configStorage = $configStorage;
    }

    /**
     * Generate a configuration file for a CN.
     *
     * @param string $commonName the CN
     *
     * @return string the config file for the CN
     */
    public function get($commonName)
    {
        $configData = [];

        // read the CN configuration
        $cnConfig = $this->configStorage->getConfig($commonName);

        // is the CN disabled?
        if ($cnConfig->getDisable()) {
            return false;
        }

        // get the pool
        $cnPool = $cnConfig->getPool();
        $poolRange = $this->v4->getPool($cnPool)->getRange();
        $v4s = $poolRange->getFirstHost();
        $v4e = $poolRange->getLastHost();
        $v4n = $this->v4->getRange()->getNetmask();
        $v6p = $this->v6->getPrefix();
        $v6r = AddressPool::getIp6($v6p, $this->v4->getRange()->getFirstHost());

        $activeLeases = array_merge(
            [$this->v4->getRange()->getFirstHost()], // the IP address of the VPN server cannot be used 
            Utils::getActiveLeases($this->leaseDir)
        );

        if (false === $v4 = AddressPool::getIp4($v4s, $v4e, $activeLeases)) {
            throw new RuntimeException(sprintf('"%s" could not connect, ran out of IP space', $envData['common_name']));
        }
        $v6 = AddressPool::getIp6($v6p, $v4);

        $configData['pool'] = $cnPool;
        $configData['v4'] = $v4;
        $configData['v4_netmask'] = $v4n;
        $configData['v4_gw'] = $this->v4->getRange()->getFirstHost();
        $configData['v6'] = $v6;
        $configData['v6_gw'] = $v6r;
        $configData['dns'] = array_merge($this->v4->getDns(), $this->v6->getDns());
        $configData['default_gw'] = $this->v4->getPool($cnPool)->useDefaultGateway();
        $configData['dst_net4'] = $this->v4->getPool($cnPool)->getDstNet4();
        // XXX we should not have v6 prefixes in v4 config section
        $configData['dst_net6'] = $this->v4->getPool($cnPool)->getDstNet6();

        return $configData;
    }

    /**
     * Parse and validate the configuration and set default values if they
     * are missing from the configuration file.
     */
    private function parseConfig(array $input)
    {
        foreach (['leaseDir', 'v4', 'v6'] as $k) {
            if (!array_key_exists($k, $input)) {
                throw new RuntimeException(sprintf('missing key "%s" in configuration', $k));
            }
        }

        $this->leaseDir = $input['leaseDir'];

        $this->v4 = new IPv4($input['v4']);
        $this->v6 = new IPv6($input['v6']);
    }
}
