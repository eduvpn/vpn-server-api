<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server;

use DateTime;
use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\TokenStorageInterface;
use PDO;
use PDOException;

class Storage implements TokenStorageInterface
{
    /** @var \PDO */
    private $db;

    /** @var \DateTime */
    private $dateTime;

    public function __construct(PDO $db, DateTime $dateTime)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->query('PRAGMA foreign_keys = ON');
        }

        $this->db = $db;
        $this->dateTime = $dateTime;
    }

    /**
     * @return array
     */
    public function getUsers()
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        user_id, 
        date_time,
        totp_secret,
        yubi_key_id,
        is_disabled
    FROM 
        users
SQL
        );
        $stmt->execute();

        $userList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $userList[] = [
                'user_id' => $row['user_id'],
                'is_disabled' => (bool) $row['is_disabled'],
                'has_yubi_key_id' => null !== $row['yubi_key_id'],
                'has_totp_secret' => null !== $row['totp_secret'],
            ];
        }

        return $userList;
    }

    /**
     * @param string $commonName
     *
     * @return array|false
     */
    public function getUserCertificateInfo($commonName)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT 
        u.user_id AS user_id, 
        u.is_disabled AS user_is_disabled,
        c.display_name AS display_name,
        c.is_disabled AS certificate_is_disabled 
    FROM 
        users u, certificates c 
    WHERE 
        u.user_id = c.user_id AND 
        c.common_name = :common_name
SQL
        );

        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $userId
     *
     * @return string|null
     */
    public function getVootToken($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        voot_token
    FROM 
        users
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function setVootToken($userId, AccessToken $vootToken)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        voot_token = :voot_token
    WHERE
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':voot_token', $vootToken->toJson(), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return bool
     */
    public function hasVootToken($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        voot_token
    FROM 
        users
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return null !== $stmt->fetchColumn();
    }

    public function deleteVootToken($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        voot_token = NULL
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return bool
     */
    public function hasTotpSecret($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        totp_secret
    FROM 
        users
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return null !== $stmt->fetchColumn();
    }

    /**
     * @param string $userId
     *
     * @return string|null
     */
    public function getTotpSecret($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        totp_secret
    FROM 
        users
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function setTotpSecret($userId, $totpSecret)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        totp_secret = :totp_secret
    WHERE
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':totp_secret', $totpSecret, PDO::PARAM_STR);

        $stmt->execute();
    }

    public function deleteTotpSecret($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        totp_secret = NULL
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function setYubiKeyId($userId, $yubiKeyId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        yubi_key_id = :yubi_key_id
    WHERE
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':yubi_key_id', $yubiKeyId, PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return bool
     */
    public function hasYubiKeyId($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        yubi_key_id
    FROM 
        users
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return null !== $stmt->fetchColumn();
    }

    /**
     * @param string $userId
     *
     * @return string|null
     */
    public function getYubiKeyId($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        yubi_key_id
    FROM 
        users
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function deleteYubiKeyId($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        yubi_key_id = NULL
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function deleteUser($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    DELETE FROM 
        users 
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function addCertificate($userId, $commonName, $displayName, DateTime $validFrom, DateTime $validTo)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    INSERT INTO certificates 
        (common_name, user_id, display_name, valid_from, valid_to)
    VALUES
        (:common_name, :user_id, :display_name, :valid_from, :valid_to)
SQL
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':valid_from', $validFrom->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':valid_to', $validTo->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getCertificates($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        common_name, 
        display_name, 
        valid_from, 
        valid_to, 
        is_disabled
    FROM 
        certificates
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        $certificateList = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['is_disabled'] = (bool) $row['is_disabled'];
            $certificateList[] = $row;
        }

        return $certificateList;
    }

    public function disableCertificate($commonName)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE 
        certificates 
    SET 
        is_disabled = 1 
    WHERE
        common_name = :common_name
SQL
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function deleteCertificate($commonName)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    DELETE FROM 
        certificates 
    WHERE 
        common_name = :common_name
SQL
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function enableCertificate($commonName)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE 
        certificates 
    SET 
        is_disabled = 0 
    WHERE 
        common_name = :common_name
SQL
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function disableUser($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users 
    SET 
        is_disabled = 1 
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function enableUser($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users 
    SET 
        is_disabled = 0 
    WHERE 
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return bool
     */
    public function isDisabledUser($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        is_disabled
    FROM 
        users
    WHERE 
        user_id = :user_id 
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array
     */
    public function getAllLogEntries()
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT 
        user_id,
        common_name, 
        connected_at, 
        disconnected_at, 
        bytes_transferred
    FROM 
        connection_log
    WHERE
        disconnected_at IS NOT NULL
    ORDER BY
        connected_at
SQL
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientConnect($profileId, $commonName, $ip4, $ip6, DateTime $connectedAt)
    {
        // this query is so complex, because we want to store the user_id in the
        // log as well, not just the common_name... the user may delete the
        // certificate, or the user account may be deleted...
        $stmt = $this->db->prepare(
<<< 'SQL'
    INSERT INTO connection_log 
        (
            user_id,
            profile_id,
            common_name,
            ip4,
            ip6,
            connected_at
        ) 
    VALUES
        (
            (
                SELECT
                    u.user_id
                FROM 
                    users u, certificates c
                WHERE
                    u.user_id = c.user_id
                AND
                    c.common_name = :common_name
            ),                
            :profile_id, 
            :common_name,
            :ip4,
            :ip6,
            :connected_at
        )
SQL
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function clientDisconnect($profileId, $commonName, $ip4, $ip6, DateTime $connectedAt, DateTime $disconnectedAt, $bytesTransferred)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE 
        connection_log
    SET 
        disconnected_at = :disconnected_at, 
        bytes_transferred = :bytes_transferred
    WHERE 
        profile_id = :profile_id 
    AND
        common_name = :common_name 
    AND
        ip4 = :ip4 
    AND
        ip6 = :ip6 
    AND
        connected_at = :connected_at
SQL
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':disconnected_at', $disconnectedAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':bytes_transferred', $bytesTransferred, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @param string $ipAddress
     *
     * @return array|false
     */
    public function getLogEntry(DateTime $dateTime, $ipAddress)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT 
        user_id,
        profile_id, 
        common_name, 
        ip4, 
        ip6, 
        connected_at, 
        disconnected_at
    FROM
        connection_log
    WHERE
        (ip4 = :ip_address OR ip6 = :ip_address)
    AND 
        connected_at < :date_time
    AND 
        (disconnected_at > :date_time OR disconnected_at IS NULL)
SQL
        );
        $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();

        // XXX can this also contain multiple results? I don't think so, but
        // make sure!
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $userId
     *
     * @return int
     */
    public function getTotpAttemptCount($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
        SELECT
            COUNT(*)
        FROM 
            totp_log
        WHERE user_id = :user_id
SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param string $userId
     * @param string $totpKey
     *
     * @return bool true if recording succeeds, false if it cannot due to replay
     */
    public function recordTotpKey($userId, $totpKey)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    INSERT INTO totp_log 
        (user_id, totp_key, date_time)
    VALUES
        (:user_id, :totp_key, :date_time)
SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':totp_key', $totpKey, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            // unable to record the TOTP, most likely replay
            return false;
        }

        return true;
    }

    public function cleanConnectionLog(DateTime $dateTime)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    DELETE FROM
        connection_log
    WHERE
        connected_at < :date_time
    AND
        disconnected_at IS NOT NULL
SQL
        );

        $stmt->bindValue(':date_time', $dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function cleanUserMessages(DateTime $dateTime)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    DELETE FROM
        user_messages
    WHERE
        date_time < :date_time
SQL
        );

        $stmt->bindValue(':date_time', $dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function cleanTotpLog(DateTime $dateTime)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    DELETE FROM 
        totp_log
    WHERE 
        date_time < :date_time
SQL
        );

        $stmt->bindValue(':date_time', $dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * @param string $type
     *
     * @return array
     */
    public function systemMessages($type)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        id, message, date_time 
    FROM 
        system_messages
    WHERE
        type = :type
SQL
        );

        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addSystemMessage($type, $message)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    INSERT INTO system_messages 
        (type, message, date_time) 
    VALUES
        (:type, :message, :date_time)
SQL
        );

        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function deleteSystemMessage($messageId)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    DELETE FROM 
        system_messages
    WHERE id = :message_id
SQL
        );

        $stmt->bindValue(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function userMessages($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        id, type, message, date_time 
    FROM 
        user_messages
    WHERE
        user_id = :user_id
    ORDER BY
        date_time DESC
SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addUserMessage($userId, $type, $message)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    INSERT INTO user_messages 
        (user_id, type, message, date_time) 
    VALUES
        (:user_id, :type, :message, :date_time)
SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }

    // TokenStorageInterface

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getAccessTokenList($userId)
    {
        $vootToken = $this->getVootToken($userId);
        if (null === $vootToken) {
            return [];
        }

        return [
            AccessToken::fromJson($vootToken),
        ];
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
     */
    public function storeAccessToken($userId, AccessToken $accessToken)
    {
        $this->setVootToken($userId, $accessToken);
    }

    /**
     * @param string      $userId
     * @param AccessToken $accessToken
     */
    public function deleteAccessToken($userId, AccessToken $accessToken)
    {
        $this->deleteVootToken($userId);
    }

    public function init()
    {
        $queryList = [];
        $queryList[] =
<<< 'SQL'
    CREATE TABLE IF NOT EXISTS users (
        user_id VARCHAR(255) NOT NULL PRIMARY KEY UNIQUE,
        voot_token TEXT DEFAULT NULL,
        totp_secret VARCHAR(255) DEFAULT NULL,
        yubi_key_id VARCHAR(255) DEFAULT NULL,
        date_time DATETIME NOT NULL,
        is_disabled BOOLEAN DEFAULT 0 NOT NULL
    )
SQL;

        $queryList[] =
<<< 'SQL'
    CREATE TABLE IF NOT EXISTS certificates (
        common_name VARCHAR(255) UNIQUE NOT NULL,
        display_name VARCHAR(255) NOT NULL,
        valid_from DATETIME NOT NULL,
        valid_to DATETIME NOT NULL,
        is_disabled BOOLEAN DEFAULT 0,
        user_id VARCHAR(255) NOT NULL REFERENCES users(user_id) ON DELETE CASCADE
    )
SQL;

        $queryList[] =
<<< 'SQL'
    CREATE TABLE IF NOT EXISTS totp_log (
        totp_key VARCHAR(255) NOT NULL,
        date_time DATETIME NOT NULL,
        user_id VARCHAR(255) NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
        UNIQUE (user_id, totp_key)
    )
SQL;

        $queryList[] =
<<< 'SQL'
    CREATE TABLE IF NOT EXISTS connection_log (
        user_id VARCHAR(255) NOT NULL,
        common_name VARCHAR(255) NOT NULL,
        profile_id VARCHAR(255) NOT NULL,
        ip4 VARCHAR(255) NOT NULL,
        ip6 VARCHAR(255) NOT NULL,
        connected_at DATETIME NOT NULL,
        disconnected_at DATETIME DEFAULT NULL,
        bytes_transferred INTEGER DEFAULT NULL                
    )
SQL;

        $queryList[] =
<<< 'SQL'
    CREATE TABLE IF NOT EXISTS system_messages (
        id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        type VARCHAR(255) NOT NULL DEFAULT "notification",
        message TINYTEXT NOT NULL,
        date_time DATETIME NOT NULL
    )
SQL;

        $queryList[] =
<<< 'SQL'
    CREATE TABLE IF NOT EXISTS user_messages (
        id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        type VARCHAR(255) NOT NULL DEFAULT "notification",
        message TINYTEXT NOT NULL,
        date_time DATETIME NOT NULL,
        user_id VARCHAR(255) NOT NULL REFERENCES users(user_id) ON DELETE CASCADE
    )
SQL;

        foreach ($queryList as $query) {
            if ('sqlite' === $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                $query = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $query);
            }
            $this->db->query($query);
        }
    }

    private function addUser($userId)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT 
        COUNT(*)
    FROM 
        users
    WHERE user_id = :user_id
SQL
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== (int) $stmt->fetchColumn()) {
            // user does not exist yet
            $stmt = $this->db->prepare(
<<< 'SQL'
    INSERT INTO 
        users (
            user_id,
            date_time
        )
    VALUES (
        :user_id,
        :date_time
    )
SQL
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':date_time', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->execute();
        }
    }
}
