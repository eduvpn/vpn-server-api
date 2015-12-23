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

class Api
{
    /** @var Socket */
    private $socket;

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;

        // turn off log that may mess up our responses
        $this->socket->command('log off');
    }

    /**
     * Get information about the connected clients.
     *
     * @return array information about the connected clients
     */
    public function getStatus()
    {
        $response = $this->socket->command('status 2');

        return StatusParser::parse($response);
    }

    /**
     * Get the OpenVPN version string.
     * 
     * @return string the OpenVPN version string
     */
    public function getVersion()
    {
        #OpenVPN Version: OpenVPN 2.3.9 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Dec 16 2015
        #Management Version: 1
        #END
        $response = $this->socket->command('version');

        return substr(
            $response[0],
            strlen('OpenVPN Version: ')
        );
    }

    /**
     * Get the current load statistics from the OpenVPN instance.
     * 
     * @return array some load statistics
     */
    public function getLoadStats()
    {
        #SUCCESS: nclients=2,bytesin=8303338,bytesout=12680522
        $loadStats = array();
        $keyMapping = array(
            'nclients' => 'number_of_clients',
            'bytesin' => 'bytes_in',
            'bytesout' => 'bytes_out',
        );
        $response = $this->socket->command('load-stats');
        $statArray = explode(',', substr($response[0], strlen('SUCCESS: ')));
        foreach ($statArray as $statItem) {
            list($key, $value) = explode('=', $statItem);
            if (array_key_exists($key, $keyMapping)) {
                $loadStats[$keyMapping[$key]] = intval($value);
            }
        }

        return $loadStats;
    }

    /**
     * Kill a currently connected client.
     *
     * @param string $commonName the common name of the connection to kill
     *
     * @return bool true on success, false on failure
     */
    public function killClient($commonName)
    {
        #ERROR: common name 'sdkjfhksjdhfsdf' not found
        #SUCCESS: common name 'fkooman_ziptest' found, 1 client(s) killed
        $response = $this->socket->command(sprintf('kill %s', $commonName));

        return 0 === strpos($response[0], 'SUCCESS: ');
    }
}
