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

require_once __DIR__.'/Test/TestSocket.php';

use fkooman\Http\Request;
use PHPUnit_Framework_TestCase;
use fkooman\VPN\Server\Test\TestSocket;

class ServerServiceTest extends PHPUnit_Framework_TestCase
{
    public function testVersionNoServers()
    {
        $request = new Request(
            array(
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/api.php/version',
                'PATH_INFO' => '/version',
            )
        );

        $m = new ServerManager();
        $serverOne = new ServerApi(new TestSocket(self::readFile('openvpn_23_version.txt')));
        $serverTwo = new ServerApi(new TestSocket(self::readFile('openvpn_23_version.txt')));
        $m->addServer('one', 'One', $serverOne);
        $m->addServer('two', 'Two', $serverTwo);
        $ccd = new CcdHandler('/foo');
        $crl = new CrlFetcher('http://localhost/ca.crl', '/foo');
        $service = new ServerService($m, $ccd, $crl);
        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/json',
                'Content-Length: 307',
                '',
                '{"items":[{"id":"one","name":"One","version":"OpenVPN 2.3.8 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Aug  4 2015"},{"id":"two","name":"Two","version":"OpenVPN 2.3.8 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Aug  4 2015"}]}',
            ),
            $service->run($request)->toArray()
        );
    }

    private static function readFile($fileName)
    {
        return @file_get_contents(
            sprintf(__DIR__.'/data/socket/%s', $fileName)
        );
    }
}
