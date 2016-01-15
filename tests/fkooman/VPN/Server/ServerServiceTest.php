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
use PDO;

class ServerServiceTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $service;

    public function setUp()
    {
        $m = new ServerManager();
        $serverOne = new ServerApi('one', new TestSocket(self::readFile('openvpn_23_version.txt')));
        $m->addServer($serverOne);
        $ccd = new CcdHandler('/foo');
        $crl = new CrlFetcher('http://localhost/ca.crl', '/foo');

        $cc = new ClientConnection(new PDO('sqlite::memory:'));
        $cc->initDatabase();

        $this->service = new ServerService($m, $ccd, $crl, $cc);
    }

    public function testVersion()
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

        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/json',
                'Content-Length: 156',
                '',
                '{"items":[{"id":"one","ok":true,"version":"OpenVPN 2.3.8 x86_64-redhat-linux-gnu [SSL (OpenSSL)] [LZO] [EPOLL] [PKCS11] [MH] [IPv6] built on Aug  4 2015"}]}',
            ),
            $this->service->run($request)->toArray()
        );
    }

    public function testConnectionLog()
    {
        $request = new Request(
            array(
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 80,
                'REQUEST_URI' => '/api.php/log/history',
                'PATH_INFO' => '/log/history',
            )
        );

        $this->assertSame(
            array(
                'HTTP/1.1 200 OK',
                'Content-Type: application/json',
                'Content-Length: 24',
                '',
                '{"ok":true,"history":[]}',
            ),
            $this->service->run($request)->toArray()
        );
    }

    private static function readFile($fileName)
    {
        return @file_get_contents(
            sprintf(__DIR__.'/data/socket/%s', $fileName)
        );
    }
}
