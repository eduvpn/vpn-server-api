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

namespace SURFnet\VPN\Server;

use PDO;
use SURFnet\VPN\Common\RandomInterface;

class Storage
{
    /** @var \PDO */
    private $db;

    /** @var \SURFnet\VPN\Common\RandomInterface */
    private $random;

    public function __construct(PDO $db, RandomInterface $random)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        // enable foreign keys
        $this->db->query('PRAGMA foreign_keys = ON');

        $this->random = $random;
    }

    public function getUsers()
    {
        $stmt = $this->db->prepare(
            'SELECT external_user_id, is_disabled
             FROM users'
        );
        $stmt->execute();

        $userList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $result) {
            $userList[] = [
                'user_id' => $result['external_user_id'],
                'is_disabled' => boolval($result['is_disabled']),
            ];
        }

        return $userList;
    }

    public function getUserCertificateStatus($commonName)
    {
        $stmt = $this->db->prepare(
            'SELECT 
                u.external_user_id AS external_user_id, 
                u.is_disabled AS user_is_disabled, 
                c.is_disabled AS certificate_is_disabled 
             FROM users u, certificates c 
             WHERE c.common_name = :common_name'
        );

        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getUserId($externalUserId)
    {
        $stmt = $this->db->prepare(
            'SELECT user_id
             FROM users
             WHERE external_user_id = :external_user_id'
        );
        $stmt->bindValue(':external_user_id', $externalUserId, PDO::PARAM_STR);
        $stmt->execute();

        if (false !== $result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $result['user_id'];
        }

        // user does not exist yet, add it
        $stmt = $this->db->prepare(
            'INSERT INTO users (external_user_id, user_id) VALUES(:external_user_id, :user_id)'
        );

        $userId = $this->random->get(16);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':external_user_id', $externalUserId, PDO::PARAM_STR);
        $stmt->execute();

        return $userId;
    }

    public function setVootToken($externalUserId, $vootToken)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'INSERT INTO voot_tokens (user_id, voot_token) VALUES(:user_id, :voot_token)'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':voot_token', $vootToken, PDO::PARAM_STR);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function hasVootToken($externalUserId)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM voot_tokens
             WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === intval($stmt->fetchColumn());
    }

    public function deleteVootToken($externalUserId)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'DELETE FROM voot_tokens WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function hasTotpSecret($externalUserId)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM totp_secrets
             WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === intval($stmt->fetchColumn());
    }

    public function getTotpSecret($externalUserId)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'SELECT totp_secret
             FROM totp_secrets
             WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function setTotpSecret($externalUserId, $totpSecret)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'INSERT INTO totp_secrets (user_id, totp_secret) VALUES(:user_id, :totp_secret)'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':totp_secret', $totpSecret, PDO::PARAM_STR);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteTotpSecret($externalUserId)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'DELETE FROM totp_secrets WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteUser($externalUserId)
    {
        $stmt = $this->db->prepare(
            'DELETE FROM users WHERE external_user_id = :external_user_id'
        );
        $stmt->bindValue(':external_user_id', $externalUserId, PDO::PARAM_STR);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function addCertificate($externalUserId, $commonName, $displayName, $validFrom, $validTo)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'INSERT INTO certificates (common_name, user_id, display_name, valid_from, valid_to) VALUES(:common_name, :user_id, :display_name, :valid_from, :valid_to)'
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':valid_from', $validFrom, PDO::PARAM_INT);
        $stmt->bindValue(':valid_to', $validTo, PDO::PARAM_INT);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getCertificates($externalUserId)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'SELECT common_name, display_name, valid_from, valid_to, is_disabled
             FROM certificates
             WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $certificateList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $result) {
            $certificateList[] = [
                'common_name' => $result['common_name'],
                'display_name' => $result['display_name'],
                'valid_from' => intval($result['valid_from']),
                'valid_to' => intval($result['valid_to']),
                'is_disabled' => boolval($result['is_disabled']),
            ];
        }

        return $certificateList;
    }

    public function disableCertificate($commonName)
    {
        $stmt = $this->db->prepare(
            'UPDATE certificates SET is_disabled = 1 WHERE common_name = :common_name'
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function enableCertificate($commonName)
    {
        $stmt = $this->db->prepare(
            'UPDATE certificates SET is_disabled = 0 WHERE common_name = :common_name'
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function disableUser($externalUserId)
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET is_disabled = 1 WHERE external_user_id = :external_user_id'
        );
        $stmt->bindValue(':external_user_id', $externalUserId, PDO::PARAM_STR);

        $stmt->execute();

        // XXX it seems on update the rowCount is always 1, even if nothing was
        // modified?
        return 1 === $stmt->rowCount();
    }

    public function enableUser($externalUserId)
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET is_disabled = 0 WHERE external_user_id = :external_user_id'
        );
        $stmt->bindValue(':external_user_id', $externalUserId, PDO::PARAM_STR);

        $stmt->execute();

        // XXX it seems on update the rowCount is always 1, even if nothing was
        // modified?
        return 1 === $stmt->rowCount();
    }

    public function isDisabledUser($externalUserId)
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM users
             WHERE external_user_id = :external_user_id AND is_disabled = 1'
        );
        $stmt->bindValue(':external_user_id', $externalUserId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === intval($stmt->fetchColumn());
    }

    public function clientConnect($profileId, $commonName, $ip4, $ip6, $connectedAt)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO connection_log (
                profile_id,
                common_name,
                ip4,
                ip6,
                connected_at
             ) 
             VALUES(
                :profile_id, 
                :common_name,
                :ip4,
                :ip6,
                :connected_at
             )'
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt, PDO::PARAM_INT);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function clientDisconnect($profileId, $commonName, $ip4, $ip6, $connectedAt, $disconnectedAt, $bytesTransferred)
    {
        $stmt = $this->db->prepare(
            'UPDATE connection_log
                SET 
                    disconnected_at = :disconnected_at, 
                    bytes_transferred = :bytes_transferred
                WHERE 
                    profile_id = :profile_id AND
                    common_name = :common_name AND
                    ip4 = :ip4 AND
                    ip6 = :ip6 AND
                    connected_at = :connected_at
            '
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt, PDO::PARAM_INT);
        $stmt->bindValue(':disconnected_at', $disconnectedAt, PDO::PARAM_INT);
        $stmt->bindValue(':bytes_transferred', $bytesTransferred, PDO::PARAM_INT);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getLogEntry($dateTimeUnix, $ipAddress)
    {
        $stmt = $this->db->prepare(
            'SELECT profile_id, common_name, ip4, ip6, connected_at, disconnected_at
             FROM connection_log
             WHERE
                (ip4 = :ip_address OR ip6 = :ip_address)
                AND connected_at < :date_time_unix
                AND (disconnected_at > :date_time_unix OR disconnected_at IS NULL)'
        );
        $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindValue(':date_time_unix', $dateTimeUnix, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recordTotpKey($externalUserId, $totpKey, $timeUnix)
    {
        $userId = $this->getUserId($externalUserId);
        $stmt = $this->db->prepare(
            'INSERT INTO totp_log (
                user_id,
                totp_key,
                time_unix
             ) 
             VALUES(
                :user_id, 
                :totp_key,
                :time_unix
             )'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':totp_key', $totpKey, PDO::PARAM_STR);
        $stmt->bindValue(':time_unix', $timeUnix, PDO::PARAM_INT);

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function cleanConnectionLog($timeUnix)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM connection_log
                    WHERE connected_at < :time_unix'
            )
        );

        $stmt->bindValue(':time_unix', $timeUnix, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function cleanTotpLog($timeUnix)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM totp_log
                    WHERE time_unix < :time_unix'
            )
        );

        $stmt->bindValue(':time_unix', $timeUnix, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function motd()
    {
        $stmt = $this->db->prepare(
            'SELECT motd_message FROM motd'
        );

        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function setMotd($motdMessage)
    {
        $this->deleteMotd();

        $stmt = $this->db->prepare(
            'INSERT INTO motd (motd_message) VALUES(:motd_message)'
        );

        $stmt->bindValue(':motd_message', $motdMessage, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteMotd()
    {
        $stmt = $this->db->prepare(
            'DELETE FROM motd'
        );

        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function init()
    {
        $queryList = [
            'CREATE TABLE IF NOT EXISTS users (
                user_id VARCHAR(255) PRIMARY KEY,
                external_user_id VARCHAR(255) UNIQUE NOT NULL,
                is_disabled BOOLEAN DEFAULT 0
            )',
            'CREATE TABLE IF NOT EXISTS voot_tokens (
                voot_token VARCHAR(255) NOT NULL PRIMARY KEY,   
                user_id VARCHAR(255) UNIQUE NOT NULL REFERENCES users(user_id) ON DELETE CASCADE
            )',
            'CREATE TABLE IF NOT EXISTS totp_secrets (
                totp_secret VARCHAR(255) NOT NULL PRIMARY KEY,   
                user_id VARCHAR(255) UNIQUE NOT NULL REFERENCES users(user_id) ON DELETE CASCADE
            )',
            'CREATE TABLE IF NOT EXISTS certificates (
                common_name VARCHAR(255) NOT NULL PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
                display_name VARCHAR(255) NOT NULL,
                valid_from INTEGER NOT NULL,
                valid_to INTEGER NOT NULL,
                is_disabled BOOLEAN DEFAULT 0
            )',
            'CREATE TABLE IF NOT EXISTS connection_log (
                common_name VARCHAR(255) NOT NULL REFERENCES certificates(common_name),
                profile_id VARCHAR(255) NOT NULL,
                ip4 VARCHAR(255) NOT NULL,
                ip6 VARCHAR(255) NOT NULL,
                connected_at INTEGER NOT NULL,
                disconnected_at INTEGER DEFAULT NULL,
                bytes_transferred INTEGER DEFAULT NULL                
            )',
            'CREATE TABLE IF NOT EXISTS totp_log (
                user_id VARCHAR(255) NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
                totp_key VARCHAR(255) NOT NULL,
                time_unix INTEGER NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS motd (
                motd_message TEXT
            )',
        ];

        foreach ($queryList as $query) {
            $this->db->query($query);
        }
    }
}
