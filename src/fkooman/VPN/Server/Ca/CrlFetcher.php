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
namespace fkooman\VPN\Server\Ca;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

/**
 * Fetch and store the CRL used by OpenVPN.
 */
class CrlFetcher
{
    /** @var string */
    private $crlUrl;

    /** @var string */
    private $crlPath;

    /** @var \GuzzleHttp\Client */
    private $client;

    /**
     * @param string $crlUrl  the URL location to fetch from
     * @param string $crlPath the folder to write to
     */
    public function __construct($crlUrl, $crlPath, Client $client = null)
    {
        $this->crlUrl = $crlUrl;
        $this->crlPath = $crlPath;
        if (null === $client) {
            $client = new Client();
        }
        $this->client = $client;
    }

    /**
     * Fetch and store the CRL.
     */
    public function fetch()
    {
        $crlFile = sprintf('%s/ca.crl', $this->crlPath);
        $tmpFile = sprintf('%s.tmp', $crlFile);

        try {
            $response = $this->client->get($this->crlUrl);
            $crlData = $response->getBody();

            if (!is_dir($this->crlPath)) {
                @mkdir($this->crlPath);
            }

            if (false === @file_put_contents($tmpFile, $crlData)) {
                // unable to write tmp file
                return ['ok' => false, 'error' => 'unable to write CRL'];
            }

            if (false === @rename($tmpFile, $crlFile)) {
                // unable to rename tmp file to crl file
                if (false === @unlink($tmpFile)) {
                    return ['ok' => false, 'error' => 'unable to delete temporary CRL'];
                }

                return ['ok' => false, 'error' => 'unable to rename temporary CRL to active CRL'];
            }

            // succeeded        
            return ['ok' => true];
        } catch (TransferException $e) {
            // Guzzle catch all exception
            return ['ok' => false, 'error' => 'unable to download CRL'];
        }
    }
}
