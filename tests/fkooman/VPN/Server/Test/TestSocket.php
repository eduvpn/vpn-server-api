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
namespace fkooman\VPN\Server\Test;

use fkooman\VPN\Server\ServerSocketInterface;
use fkooman\VPN\Server\Exception\ServerSocketException;

/**
 * Abstraction to use the OpenVPN management interface using a socket 
 * connection.
 */
class TestSocket implements ServerSocketInterface
{
    /** @var resource */
    private $returnData;

    private $connectFail;

    public function __construct($returnData, $connectFail = false)
    {
        $this->returnData = $returnData;
        $this->connectFail = $connectFail;
    }

    public function open()
    {
        if ($this->connectFail) {
            throw new ServerSocketException('unable to connect to socket');
        }
    }

    public function command($command)
    {
        // send back the returnData as an array
        return explode("\n", $this->returnData);
    }

    public function close()
    {
    }
}
