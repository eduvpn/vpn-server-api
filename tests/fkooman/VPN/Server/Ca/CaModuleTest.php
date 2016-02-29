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

use fkooman\Rest\Service;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\Dummy\DummyAuthentication;
use fkooman\Rest\Plugin\Authentication\AuthenticationPlugin;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use Psr\Log\NullLogger;

class CaModuleTest extends PHPUnit_Framework_TestCase
{
    /** @var \fkooman\Rest\Service */
    private $server;

    public function setUp()
    {
        $client = new Client();
        $mock = new Mock(
            array(
                new Response(
                    200,
                    array('Content-Type' => 'text/html'),
                    Stream::factory(
                        'this is the CRL'
                    )
                ),
            )
        );
        $client->getEmitter()->attach($mock);

        $crlFetcher = new CrlFetcher('http://example.org/ca.crt', sprintf('%s/%s', sys_get_temp_dir(), 'crl'), $client);
        $caModule = new CaModule($crlFetcher, new NullLogger());

        $this->service = new Service();
        $this->service->addModule($caModule);
        $dummyAuth = new DummyAuthentication('foo');
        $authenticationPlugin = new AuthenticationPlugin();
        $authenticationPlugin->register($dummyAuth, 'api');
        $this->service->getPluginRegistry()->registerDefaultPlugin($authenticationPlugin);
    }

    public function testFetchCrl()
    {
        $this->assertSame(
            [
                'ok' => true,
            ],
            $this->makeRequest('POST', '/crl/fetch', [])
        );
    }

    private function makeRequest($requestMethod, $requestUri, array $queryBody = [])
    {
        if ('GET' === $requestMethod || 'DELETE' === $requestMethod) {
            return $this->service->run(
                new Request(
                    array(
                        'SERVER_NAME' => 'www.example.org',
                        'SERVER_PORT' => 80,
                        'REQUEST_METHOD' => $requestMethod,
                        'REQUEST_URI' => sprintf('%s?%s', $requestUri, http_build_query($queryBody)),
                        'PATH_INFO' => $requestUri,
                        'QUERY_STRING' => http_build_query($queryBody),
                    )
                )
            )->getBody();
        } else {
            // POST
            return $this->service->run(
                new Request(
                    array(
                        'SERVER_NAME' => 'www.example.org',
                        'SERVER_PORT' => 80,
                        'REQUEST_METHOD' => $requestMethod,
                        'REQUEST_URI' => $requestUri,
                        'PATH_INFO' => $requestUri,
                        'QUERY_STRING' => '',
                    ),
                    $queryBody
                )
            )->getBody();
        }
    }
}
