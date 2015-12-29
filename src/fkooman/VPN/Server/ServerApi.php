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

/**
 * Higher level abstraction of ServerSocket providing a cleaner API that 
 * performs some post processing making it easier for applications to use.
 */
class ServerApi implements ServerApiInterface
{
    /** @var SocketInterface */
    private $socket;

    public function __construct(ServerSocketInterface $socket)
    {
        $this->socket = $socket;
    }

    public function connect()
    {
        $this->socket->open();
    }

    public function disconnect()
    {
        $this->socket->close();
    }

    /**
     * Obtain information about connected clients.
     *
     * @return array information about the connected clients
     */
    public function status()
    {
        $response = $this->socket->command('status 2');

        return StatusParser::parse($response);
    }

    /**
     * Obtain OpenVPN version information.
     * 
     * @return string the OpenVPN version string
     */
    public function version()
    {
        $response = $this->socket->command('version');

        return substr(
            $response[0],
            strlen('OpenVPN Version: ')
        );
    }

    /**
     * Obtain the current load statistics from OpenVPN.
     * 
     * @return array load statistics
     */
    public function loadStats()
    {
        $keyMapping = array(
            'nclients' => 'number_of_clients',
            'bytesin' => 'bytes_in',
            'bytesout' => 'bytes_out',
        );

        $response = $this->socket->command('load-stats');

        $statArray = explode(',', substr($response[0], strlen('SUCCESS: ')));
        $loadStats = array();
        foreach ($statArray as $statItem) {
            list($key, $value) = explode('=', $statItem);
            if (array_key_exists($key, $keyMapping)) {
                $loadStats[$keyMapping[$key]] = intval($value);
            }
        }

        return $loadStats;
    }

    /**
     * Disconnect a client.
     *
     * @param string $commonName the common name of the connection to kill
     *
     * @return bool true on success, false on failure
     */
    public function kill($commonName)
    {
        $response = $this->socket->command(sprintf('kill %s', $commonName));

        return 0 === strpos($response[0], 'SUCCESS: ');
    }
}
