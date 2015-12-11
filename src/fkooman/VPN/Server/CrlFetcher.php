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
namespace fkooman\VPN\Server;

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
        $crlFile = sprintf('%s/ca.crl', $this->crlPath);
        $tmpFile = sprintf('%s.tmp', $crlFile);
        $crlData = $this->client->get($this->crlUrl)->getBody();

        if (false === @file_put_contents($tmpFile, $crlData)) {
            throw new RuntimeException('unable to write CRL to temporary file');
        }

        if (file_exists($crlFile)) {
            if (filesize($tmpFile) < filesize($crlFile)) {
                throw new RuntimeException('downloaded CRL size is smaller than current CRL size');
            }
        }

        if (false === @rename($tmpFile, $crlFile)) {
            throw new RuntimeException('unable to move downloaded CRL to CRL location');
        }
    }
}
