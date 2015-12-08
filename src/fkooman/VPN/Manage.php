<?php

namespace fkooman\VPN;

class Manage
{
    /** @var SocketStatus */
    private $socketStatus;

    public function __construct($socketAddress)
    {
        $this->socketStatus = new SocketStatus($socketAddress);
    }

    public function getClientInfo()
    {
        $statusParser = new StatusParser($this->socketStatus->fetchStatus());

        return $statusParser->getClientInfo();
    }

    public function killClient($commonName)
    {
        $this->socketStatus->killClient($commonName);
    }
}
