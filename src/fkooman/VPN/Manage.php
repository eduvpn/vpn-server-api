<?php

namespace fkooman\VPN;

class Manage
{
    /** @var array */
    private $socketStatus;

    public function __construct(array $socketAddresses)
    {
        foreach ($socketAddresses as $socketAddress) {
            $this->socketStatus[$socketAddress] = new SocketStatus($socketAddress);
        }
    }

    public function getClientInfo()
    {
        $combinedClientInfo = array();
        foreach ($this->socketStatus as $k => $v) {
            $statusParser = new StatusParser($k, $v->fetchStatus());
            $combinedClientInfo = array_merge($combinedClientInfo, $statusParser->getClientInfo());
        }

        return array('items' => $combinedClientInfo);
    }

    public function killClient($socketId, $commonName)
    {
        $this->socketStatus[$socketId]->killClient($commonName);
    }
}
