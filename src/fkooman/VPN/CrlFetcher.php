<?php

namespace fkooman\VPN;

use GuzzleHttp\Client;
use RuntimeException;

class CrlFetcher
{
    /** @var string */
    private $crlUrl;

    /** @var string */
    private $crlPath;

    /** @var GuzzleHttp/Client */
    private $client;

    public function __construct($crlUrl, $crlPath, Client $client = null)
    {
        $this->crlUrl = $crlUrl;
        $this->crlPath = $crlPath;
        if (null === $client) {
            $client = new Client();
        }
        $this->client = $client;
    }

    public function fetch()
    {
        $crlData = $this->client->get($crlUri)->getBody();
        $tmpFile = tempnam(sys_get_temp_dir(), 'crl');
        if (false === @file_put_contents($tmpFile, $crlData)) {
            throw new RuntimeException('unable to write CRL to temporary file');
        }

        if (file_exists($crlFile)) {
            if (filesize($tmpFile) < filesize($crlFile)) {
                throw new RuntimeException('downloaded CRL size is smaller than current CRL size');
            }
        }

        if (false === @rename($tmpFile, $crlFile)) {
            throw new Exception('unable to move downloaded CRL to CRL location');
        }
    }
}
