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

namespace fkooman\VPN\Server\OpenVpn;

use fkooman\VPN\Server\OpenVpn\Exception\ServerSocketException;

/**
 * Higher level abstraction of ServerSocket providing a cleaner API that 
 * performs some post processing making it easier for applications to use.
 */
class ServerApi
{
    /** @var ServerSocketInterface */
    private $serverSocket;

    public function __construct(ServerSocketInterface $serverSocket)
    {
        $this->serverSocket = $serverSocket;
    }

    /**
     * Obtain information about connected clients.
     *
     * @return array information about the connected clients
     */
    public function status()
    {
        try {
            $this->serverSocket->open();
            $response = $this->serverSocket->command('status 2');
            $this->serverSocket->close();

            return StatusParser::parse($response);
        } catch (ServerSocketException $e) {
            return false;
        }
    }

    /**
     * Disconnect a client.
     *
     * @param string $commonName the common name of the connection to kill
     */
    public function kill($commonName)
    {
        try {
            $this->serverSocket->open();
            $response = $this->serverSocket->command(sprintf('kill %s', $commonName));
            $this->serverSocket->close();

            return 0 === strpos($response[0], 'SUCCESS: ');
        } catch (ServerSocketException $e) {
            return false;
        }
    }
}
