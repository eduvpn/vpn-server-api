<?php

namespace fkooman\VPN\Server;

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
        // XXX validate
        $this->proto = $proto;
    }

    public function getProto()
    {
        return $this->proto;
    }

    public function setDev($dev)
    {
        // XXX validate
        $this->dev = $dev;
    }

    public function getDev()
    {
        return $this->dev;
    }

    public function setManagementPort($managementPort)
    {
        // XXX validate
        $this->managementPort = $managementPort;
    }

    public function getManagementPort()
    {
        return $this->managementPort;
    }

    public function setPort($port)
    {
        // XXX validate
        $this->port = $port;
    }

    public function getPort()
    {
        return $this->port;
    }
}
