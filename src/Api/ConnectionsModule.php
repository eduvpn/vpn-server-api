<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Api;

use DateTime;
use LC\Common\Config;
use LC\Common\Http\ApiErrorResponse;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\InputValidation;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\ProfileConfig;
use LC\Server\Api\Exception\ConnectionsModuleException;
use LC\Server\Storage;

class ConnectionsModule implements ServiceModuleInterface
{
    /** @var \DateTime */
    protected $dateTime;

    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Server\Storage */
    private $storage;

    public function __construct(Config $config, Storage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->dateTime = new DateTime();
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/connect',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                try {
                    $connectInfo = $this->connect($request);
                    $this->storage->addUserMessage($connectInfo['user_id'], 'notification', sprintf('[VPN] connect: [%s]', http_build_query($connectInfo)));

                    return new ApiResponse('connect');
                } catch (ConnectionsModuleException $e) {
                    if (null !== $userId = $e->getUserId()) {
                        $this->storage->addUserMessage($userId, 'notification', '[VPN] '.$e->getMessage());
                    }

                    return new ApiErrorResponse('connect', '[VPN] '.$e->getMessage());
                }
            }
        );

        $service->post(
            '/disconnect',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                try {
                    $disconnectInfo = $this->disconnect($request);
                    if (null !== $userId = $disconnectInfo['user_id']) {
                        $this->storage->addUserMessage($userId, 'notification', sprintf('[VPN] disconnect: [%s]', http_build_query($disconnectInfo)));
                    }

                    return new ApiResponse('disconnect');
                } catch (ConnectionsModuleException $e) {
                    if (null !== $userId = $e->getUserId()) {
                        $this->storage->addUserMessage($userId, 'notification', '[VPN] '.$e->getMessage());
                    }

                    return new ApiErrorResponse('disconnect', '[VPN] '.$e->getMessage());
                }
            }
        );
    }

    /**
     * @return array{user_id:string,profile_id:string,ip_four:string,ip_six:string}
     */
    public function connect(Request $request)
    {
        $profileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->requirePostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->requirePostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->requirePostParameter('ip6'));
        $connectedAt = InputValidation::connectedAt($request->requirePostParameter('connected_at'));

        $userId = $this->verifyConnection($profileId, $commonName);
        $this->storage->clientConnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)));

        return [
            'user_id' => $userId,
            'profile_id' => $profileId,
            'ip_four' => $ip4,
            'ip_six' => $ip6,
        ];
    }

    /**
     * @return array{user_id:string|null,bytes_transferred:int}
     */
    public function disconnect(Request $request)
    {
        $profileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->requirePostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->requirePostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->requirePostParameter('ip6'));

        $connectedAt = InputValidation::connectedAt($request->requirePostParameter('connected_at'));
        $disconnectedAt = InputValidation::disconnectedAt($request->requirePostParameter('disconnected_at'));
        $bytesTransferred = InputValidation::bytesTransferred($request->requirePostParameter('bytes_transferred'));

        $this->storage->clientDisconnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)), new DateTime(sprintf('@%d', $disconnectedAt)), $bytesTransferred);

        if (false === $userCertInfo = $this->storage->getUserCertificateInfo($commonName)) {
            // we no longer have a mapping between the certificate and a user,
            // as it was probably deleted...
            return ['user_id' => null, 'bytes_transferred' => 0];
        }

        return [
            'user_id' => (string) $userCertInfo['user_id'],
            'bytes_transferred' => $bytesTransferred,
        ];
    }

    /**
     * @param string $profileId
     * @param string $commonName
     *
     * @return string
     */
    private function verifyConnection($profileId, $commonName)
    {
        // verify status of certificate/user
        if (false === $userCertInfo = $this->storage->getUserCertificateInfo($commonName)) {
            // we do not (yet) know the user as only an existing *//* certificate can be linked back to a user...
            throw new ConnectionsModuleException(null, sprintf('user or certificate does not exist [profile_id: %s, common_name: %s]', $profileId, $commonName));
        }

        $userId = $userCertInfo['user_id'];

        if (false === strpos($userId, '!!')) {
            // FIXME "!!" indicates it is a remote guest user coming in with a
            // foreign OAuth token, for those we do NOT check expiry.. this is
            // really ugly hack, we need to get rid of sessionExpiresAt
            // completely instead! This check is skipped when a non remote
            // guest user id contains '!!' for some reason...
            //
            // this is always string, but DB gives back scalar|null
            $sessionExpiresAt = new DateTime((string) $this->storage->getSessionExpiresAt($userId));
            if ($sessionExpiresAt->getTimestamp() < $this->dateTime->getTimestamp()) {
                throw new ConnectionsModuleException($userId, sprintf('the certificate is still valid, but the session expired at %s', $sessionExpiresAt->format(DateTime::ATOM)));
            }
        }

        if ($userCertInfo['user_is_disabled']) {
            throw new ConnectionsModuleException($userId, 'unable to connect, account is disabled');
        }

        $this->verifyAcl($profileId, $userId);

        return $userId;
    }

    /**
     * @param string $profileId
     * @param string $userId
     *
     * @return void
     */
    private function verifyAcl($profileId, $userId)
    {
        $profileConfig = new ProfileConfig($this->config->getSection('vpnProfiles')->getSection($profileId)->toArray());
        if ($profileConfig->getItem('enableAcl')) {
            // ACL is enabled for this profile
            $userPermissionList = $this->storage->getPermissionList($userId);
            $profilePermissionList = $profileConfig->getSection('aclPermissionList')->toArray();
            if (false === self::hasPermission($userPermissionList, $profilePermissionList)) {
                throw new ConnectionsModuleException($userId, sprintf('unable to connect, user permissions are [%s], but requires any of [%s]', implode(',', $userPermissionList), implode(',', $profilePermissionList)));
            }
        }
    }

    /**
     * @return bool
     */
    private static function hasPermission(array $userPermissionList, array $aclPermissionList)
    {
        // one of the permissions must be listed in the profile ACL list
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $aclPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
