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

interface ServerSocketInterface
{
    /**
     * Send an OpenVPN command and get the response.
     *
     * @param string $command the OpenVPN command, e.g. 'status', 'version', 'kill'
     *
     * @return array the response lines in an array, every line as element
     */
    public function command($command);

    /**
     * Close the socket connection.
     */
    public function close();
}
