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
    /** @var string */
    private $id;

    /** @var ServerSocketInterface */
    private $serverSocket;

    public function __construct($id, ServerSocketInterface $serverSocket)
    {
        $this->id = $id;
        $this->serverSocket = $serverSocket;
    }

    public function getId()
    {
        return $this->id;
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

            return $this->ok('status', StatusParser::parse($response));
        } catch (ServerSocketException $e) {
            return $this->error();
        }
    }

    /**
     * Obtain OpenVPN version information.
     * 
     * @return string the OpenVPN version string
     */
    public function version()
    {
        try {
            $this->serverSocket->open();
            $response = $this->serverSocket->command('version');
            $this->serverSocket->close();

            return $this->ok(
                'version',
                substr($response[0], strlen('OpenVPN Version: '))
            );
        } catch (ServerSocketException $e) {
            return $this->error();
        }
    }

    /**
     * Obtain the current load statistics from OpenVPN.
     * 
     * @return array load statistics
     */
    public function loadStats()
    {
        try {
            $keyMapping = array(
                'nclients' => 'number_of_clients',
                'bytesin' => 'bytes_in',
                'bytesout' => 'bytes_out',
            );

            $this->serverSocket->open();
            $response = $this->serverSocket->command('load-stats');
            $this->serverSocket->close();

            $statArray = explode(',', substr($response[0], strlen('SUCCESS: ')));
            $loadStats = array();
            foreach ($statArray as $statItem) {
                list($key, $value) = explode('=', $statItem);
                if (array_key_exists($key, $keyMapping)) {
                    $loadStats[$keyMapping[$key]] = intval($value);
                }
            }

            return $this->ok('load-stats', $loadStats);
        } catch (ServerSocketException $e) {
            return $this->error();
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

            return $this->ok('kill', 0 === strpos($response[0], 'SUCCESS: '));
        } catch (ServerSocketException $e) {
            return $this->error();
        }
    }

    private function ok($command, $response)
    {
        return array(
            'id' => $this->getId(),
            'ok' => true,
            $command => $response,
        );
    }

    private function error()
    {
        return array(
            'id' => $this->getId(),
            'ok' => false,
        );
    }
}
