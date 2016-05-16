<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

namespace fkooman\VPN\Server;

use InvalidArgumentException;

class IP
{
    /** @var string */
    private $ipAddress;

    /** @var int */
    private $ipPrefix;

    /** @var int */
    private $ipFamily;

    public function __construct($ipAddressPrefix)
    {
        // detect if there is a prefix
        $hasPrefix = false !== strpos($ipAddressPrefix, '/');
        if ($hasPrefix) {
            list($ipAddress, $ipPrefix) = explode('/', $ipAddressPrefix);
        } else {
            $ipAddress = $ipAddressPrefix;
            $ipPrefix = null;
        }

        // validate the IP address
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new IPException('invalid IP address');
        }

        $is6 = false !== strpos($ipAddress, ':');
        if ($is6) {
            if (is_null($ipPrefix)) {
                $ipPrefix = 128;
            }

            if (!is_numeric($ipPrefix) || 0 > $ipPrefix || 128 < $ipPrefix) {
                throw new IPException('IP prefix must be a number between 0 and 128');
            }
            // normalize the IPv6 address
            $ipAddress = inet_ntop(inet_pton($ipAddress));
        } else {
            if (is_null($ipPrefix)) {
                $ipPrefix = 32;
            }
            if (!is_numeric($ipPrefix) || 0 > $ipPrefix || 32 < $ipPrefix) {
                throw new IPException('IP prefix must be a number between 0 and 32');
            }
        }

        $this->ipAddress = $ipAddress;
        $this->ipPrefix = (int) $ipPrefix;
        $this->ipFamily = $is6 ? 6 : 4;
    }

    public function getAddress()
    {
        return $this->ipAddress;
    }

    public function getPrefix()
    {
        return $this->ipPrefix;
    }

    public function getAddressPrefix()
    {
        return sprintf('%s/%d', $this->getAddress(), $this->getPrefix());
    }

    public function getFamily()
    {
        return $this->ipFamily;
    }

    /**
     * IPv4 only.
     */
    public function getNetmask()
    {
        if (4 !== $this->getFamily()) {
            throw new IPException('method only for IPv4');
        }

        return long2ip(-1 << (32 - $this->getPrefix()));
    }

    /**
     * IPv4 only.
     */
    public function getNetwork()
    {
        if (4 !== $this->getFamily()) {
            throw new IPException('method only for IPv4');
        }

        return long2ip(ip2long($this->getAddress()) & ip2long($this->getNetmask()));
    }

    /**
     * IPv4 only.
     */
    public function getNumberOfHosts()
    {
        if (4 !== $this->getFamily()) {
            throw new IPException('method only for IPv4');
        }

        return pow(2, 32 - $this->getPrefix()) - 2;
    }

    public function split($networkCount)
    {
        if (!is_int($networkCount)) {
            throw new InvalidArgumentException('parameter must be integer');
        }

        if (0 !== ($networkCount & ($networkCount - 1))) {
            throw new InvalidArgumentException('parameter must be power of 2');
        }

        if (4 === $this->getFamily()) {
            return $this->split4($networkCount);
        }

        return $this->split6($networkCount);
    }

    private function split4($networkCount)
    {
        if (30 <= $this->getPrefix()) {
            throw new IPException('network too small to split up, must be bigger than /30');
        }

        if (pow(2, 32 - $this->getPrefix() - 2) < $networkCount) {
            throw new IPException('network too small to split in this many networks');
        }

        $prefix = $this->getPrefix() + log($networkCount, 2);
        $splitRanges = [];
        for ($i = 0; $i < $networkCount; ++$i) {
            $noHosts = pow(2, 32 - $prefix);
            $networkAddress = long2ip($i * $noHosts + ip2long($this->getAddress()));
            $splitRanges[] = new self($networkAddress.'/'.$prefix);
        }

        return $splitRanges;
    }

    private function split6($networkCount)
    {
        if (64 <= $this->getPrefix()) {
            throw new IPException('network too small to split up, must be bigger than /64');
        }

        if (0 !== $this->getPrefix() % 4) {
            throw new IPException('network prefix length must be divisible by 4');
        }

        if (pow(2, 64 - $this->getPrefix()) < $networkCount) {
            throw new IPException('network too small to split in this many networks');
        }

        $hexAddress = bin2hex(inet_pton($this->getAddress()));
        // strip the last digits based on prefix size
        $hexAddress = substr($hexAddress, 0, 16 - ((64 - $this->getPrefix()) / 4));

        $splitRanges = [];
        for ($i = 0; $i < $networkCount; ++$i) {
            // pad with zeros until there is enough space for or network number
            $paddedHexAddress = str_pad($hexAddress, 16 - strlen(dechex($i)), '0');
            // append the network number
            $hexAddressWithNetwork = $paddedHexAddress.dechex($i);
            // pad it to the end and convert back to IPv6 address
            $splitRanges[] = new self(sprintf('%s/64', inet_ntop(hex2bin(str_pad($hexAddressWithNetwork, 32, '0')))));
        }

        return $splitRanges;
    }

    public function __toString()
    {
        return $this->getAddressPrefix();
    }
}
