<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Tests\Acl\Provider;

use DateTime;
use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\Http\Response;
use fkooman\OAuth\Client\OAuthClient;
use fkooman\OAuth\Client\Provider;
use PDO;
use PHPUnit\Framework\TestCase;
use SURFnet\VPN\Server\Acl\Provider\VootProvider;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\Tests\TestOAuthClientRandom;
use SURFnet\VPN\Server\Tests\TestOAuthClientSession;

class VootProviderTest extends TestCase
{
    /** @var VootProvider */
    private $vootProvider;

    public function setUp()
    {
        $vootClient = $this->getMockBuilder('\fkooman\OAuth\Client\Http\HttpClientInterface')->getMock();
        $vootClient->method('send')->will(
            $this->onConsecutiveCalls(
                new Response(200, file_get_contents(sprintf('%s/data/response.json', __DIR__)), ['Content-Type' => 'application/json']),
                new Response(401, file_get_contents(sprintf('%s/data/response_invalid_token.json', __DIR__)), ['Content-Type' => 'application/json'])
            )
        );

        $storage = new Storage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            'schema',
            new DateTime()
        );
        $storage->init();
//        $storage->setAccessToken('foo', 'voot', new AccessToken('AT', 'bearer', 'groups', 'RT', new DateTime('2016-01-02')));
        $storage->storeAccessToken(
            'foo',
            AccessToken::fromJson(
                json_encode([
                    'provider_id' => 'c|a',
                    'user_id' => 'foo',
                    'access_token' => 'AT',
                    'token_type' => 'bearer',
                    'scope' => 'groups',
                    'refresh_token' => 'RT',
                    'expires_in' => 3600,
                    'issued_at' => '2016-01-02 00:00:00',
                ])
            )
        );

        $oauthClient = new OAuthClient(
            $storage,
            $vootClient
        );
        $oauthClient->setSession(new TestOAuthClientSession());
        $oauthClient->setRandom(new TestOAuthClientRandom());
        $oauthClient->setDateTime(new DateTime('2016-01-01'));
        $this->vootProvider = new VootProvider(
            $oauthClient,
            new Provider('a', 'b', 'c', 'd'),
            'https://voot.surfconext.nl/me/groups'
        );
    }

    public function testVootCall()
    {
        $this->assertSame(
            [
                'urn:collab:group:surfteams.nl:nl:surfnet:diensten:eduvpn',
                'urn:collab:group:surfteams.nl:nl:surfnet:diensten:eduvpn-test',
                'urn:collab:group:surfteams.nl:nl:surfnet:diensten:enabling_dynamic_services_2015',
                'urn:collab:group:surfteams.nl:nl:surfnet:diensten:surfcloud_utrecht_users',
            ],
            $this->vootProvider->getGroups('foo')
        );
    }

    public function testVootCallNoToken()
    {
        $this->assertSame(
            [
            ],
            $this->vootProvider->getGroups('bar')
        );
    }

    public function testVootCallInvalidToken()
    {
        // first call succeeds, second call is invalid token response
        $this->vootProvider->getGroups('foo');
        $this->assertSame(
            [
            ],
            $this->vootProvider->getGroups('foo')
        );
    }
}
