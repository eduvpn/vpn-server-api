<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use DateTime;
use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\BearerClient;
use fkooman\OAuth\Client\Http\Response;
use fkooman\OAuth\Client\OAuth2Client;
use fkooman\OAuth\Client\Provider;
use PDO;
use PHPUnit_Framework_TestCase;
use SURFnet\VPN\Server\Storage;

class VootProviderTest extends PHPUnit_Framework_TestCase
{
    /** @var VootProvider */
    private $vootProvider;

    public function setUp()
    {
        $vootClient = $this->getMockBuilder('\fkooman\OAuth\Client\Http\HttpClientInterface')->getMock();
        $vootClient->method('get')->will(
            $this->onConsecutiveCalls(
                new Response(200, file_get_contents(sprintf('%s/data/response.json', __DIR__))),
                new Response(401, file_get_contents(sprintf('%s/data/response_invalid_token.json', __DIR__)))
            )
        );

        $storage = new Storage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            ),
            new DateTime()
        );
        $storage->init();
        $storage->setAccessToken('foo', new AccessToken('AT', 'bearer', 'foo_bar', 'RT', new DateTime('2016-01-02')));

        $random = $this->getMockBuilder('fkooman\OAuth\Client\RandomInterface')->getMock();
        $random->method('get')->will($this->onConsecutiveCalls('random_1', 'random_2'));

        $oauthClient = new OAuth2Client(
            new Provider('a', 'b', 'c', 'd'),
            $vootClient,
            $random,
            new DateTime('2016-01-01')
        );

        $this->vootProvider = new VootProvider(
            new BearerClient(
                $oauthClient,
                $storage,
                null,
                null,
                new DateTime('2016-01-01')
            ),
            'https://voot.surfconext.nl/me/groups'
        );
    }

    public function testVootCall()
    {
        $this->assertSame(
            [
                [
                    'id' => 'urn:collab:group:surfteams.nl:nl:surfnet:diensten:eduvpn',
                    'displayName' => 'EduVPN',
                ],
                [
                    'id' => 'urn:collab:group:surfteams.nl:nl:surfnet:diensten:eduvpn-test',
                    'displayName' => 'eduVPN-test',
                ],
                [
                    'id' => 'urn:collab:group:surfteams.nl:nl:surfnet:diensten:enabling_dynamic_services_2015',
                    'displayName' => 'Enabling Dynamic Services 2015',
                ],
                [
                    'id' => 'urn:collab:group:surfteams.nl:nl:surfnet:diensten:surfcloud_utrecht_users',
                    'displayName' => 'SURFcloud Utrecht users',
                ],
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
