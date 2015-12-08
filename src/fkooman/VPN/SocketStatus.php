<?php

namespace fkooman\VPN;

use Socket\Raw\Factory;
use Socket\Raw\Socket;
use Exception;

class SocketStatus
{
    /** @var \Socket\Raw\Socket */
    private $socket;

    public function __construct($socketAddress)
    {
        $factory = new Factory();
        $this->socket = $factory->createClient($socketAddress);
        // read banner
        $this->readAll();

        // disable log
        $this->socket->write("log off\n");

        // read disable log output
        $this->readAll();
    }

    public function fetchStatus()
    {
        // ask for status
        $this->socket->write("status\n");

        // read and return status
        return $this->readStatus();
    }

    private function readLine()
    {
        return $this->socket->read(256, PHP_NORMAL_READ);
    }

    private function readStatus()
    {
        $msg = '';
        do {
            $inputLine = $this->readLine();
            $msg .= $inputLine;
        } while (0 !== strpos($inputLine, 'END'));

        return $msg;
    }

    private function readAll()
    {
        $availableData = $this->socket->read(8192);
        while ($this->socket->selectRead()) {
            $availableData .= $this->socket->read(8192);
        }

        return $availableData;
    }

    public function killClient($commonName)
    {
        #kill aa3f6fade450f12aa891bf066b86921344e2a1f1_phone
        #SUCCESS: common name 'aa3f6fade450f12aa891bf066b86921344e2a1f1_phone' found, 1 client(s) killed

        $this->socket->write(sprintf("kill %s\n", $commonName));
        if (0 !== strpos($this->readLine(), 'SUCCESS')) {
            throw new Exception('unable to kill client');
        }
    }
}
