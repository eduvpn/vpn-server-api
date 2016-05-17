<?php

namespace fkooman\VPN\Server;

use InvalidArgumentException;

class Instance
{
    /** @var IP */
    private $range;

    /** @var IP */
    private $range6;

    /** @var string */
    private $proto;

    /** @var string */
    private $dev;

    /** @var int */
    private $managementPort;

    /** @var int */
    private $port;

    public function __construct(array $instanceData)
    {
        $this->setRange($instanceData['range']);
        $this->setRange6($instanceData['range6']);
        $this->setProto($instanceData['proto']);
        $this->setDev($instanceData['dev']);
        $this->setManagementPort($instanceData['managementPort']);
        $this->setPort($instanceData['port']);
    }

    public function setRange(IP $range)
    {
        $this->range = $range;
    }

    public function getRange()
    {
        return $this->range;
    }

    public function setRange6(IP $range6)
    {
        $this->range6 = $range6;
    }

    public function getRange6()
    {
        return $this->range6;
    }

    public function setProto($proto)
    {
        if (!is_string($proto)) {
            throw new InvalidArgumentException('parameter must be string');
        }
        $validProtocols = ['udp', 'tcp'];
        if (!in_array($proto, $validProtocols)) {
            throw new InstanceException('invalid proto');
        }

        $this->proto = $proto;
    }

    public function getProto()
    {
        return $this->proto;
    }

    public function setDev($dev)
    {
        if (!is_string($dev)) {
            throw new InvalidArgumentException('parameter must be string');
        }
        if (0 !== strpos($dev, 'tun-')) {
            throw new InstanceException('dev must start with "tun-"');
        }
        $this->dev = $dev;
    }

    public function getDev()
    {
        return $this->dev;
    }

    public function setManagementPort($managementPort)
    {
        if (!is_int($managementPort)) {
            throw new InvalidArgumentException('parameter must be int');
        }
        if (1024 >= $managementPort || 65536 <= $managementPort) {
            throw new InstanceException('invalid port, must be positive integer between 1025 and 65535');
        }
        $this->managementPort = $managementPort;
    }

    public function getManagementPort()
    {
        return $this->managementPort;
    }

    public function setPort($port)
    {
        if (!is_int($port)) {
            throw new InvalidArgumentException('parameter must be int');
        }
        if (1024 >= $port || 65536 <= $port) {
            throw new InstanceException('invalid port, must be positive integer between 1025 and 65535');
        }
        $this->port = $port;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function toArray()
    {
        return [
            'dev' => $this->getDev(),
            'managementPort' => $this->getManagementPort(),
            'port' => $this->getPort(),
            'proto' => $this->getProto(),
            'range' => $this->getRange()->getAddressPrefix(),
            'range6' => $this->getRange6()->getAddressPrefix(),
        ];
    }
}
