<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server;

use DateTime;
use fkooman\OAuth\Client\AccessToken;
use fkooman\OAuth\Client\TokenStorageInterface;
use fkooman\Otp\OtpInfo;
use fkooman\Otp\OtpStorageInterface;
use fkooman\SqliteMigrate\Migration;
use PDO;
use PDOException;

class Storage implements TokenStorageInterface, OtpStorageInterface
{
    const CURRENT_SCHEMA_VERSION = '2018071901';

    /** @var \PDO */
    private $db;

    /** @var \DateTime */
    private $dateTime;

    /** @var \fkooman\SqliteMigrate\Migration */
    private $migration;

    /**
     * @param \PDO      $db
     * @param string    $schemaDir
     * @param \DateTime $dateTime
     */
    public function __construct(PDO $db, $schemaDir, DateTime $dateTime)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
        $this->db = $db;
        $this->migration = new Migration($db, $schemaDir, self::CURRENT_SCHEMA_VERSION);
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
        (SELECT otp_secret FROM otp WHERE user_id = users.user_id) AS otp_secret,
        yubi_key_id,
        last_authenticated_at,
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
                'has_totp_secret' => null !== $row['otp_secret'],
                'last_authenticated_at' => $row['last_authenticated_at'],
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
        c.valid_from,
        c.valid_to
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

    public function lastAuthenticatedAtPing($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        last_authenticated_at = :last_authenticated_at
    WHERE
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':last_authenticated_at', $this->dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);

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
        valid_to
    FROM 
        certificates
    WHERE 
        user_id = :user_id
    ORDER BY
        valid_from DESC
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * @param string $profileId
     *
     * @return array
     */
    public function getLogEntries($profileId)
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
        profile_id = :profile_id
    AND
        disconnected_at IS NOT NULL
    ORDER BY
        connected_at
SQL
        );

        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientConnect($profileId, $commonName, $ip4, $ip6, DateTime $connectedAt)
    {
        // update "lost" client entries when a new client connects that gets
        // the IP address of an existing entry that was not "closed" yet. This
        // may occur when the OpenVPN process dies without writing the
        // disconnect event to the log. We fix this when a new client
        // wants to connect and gets this exact same IP address...
        $stmt = $this->db->prepare(
<<< 'SQL'
        UPDATE 
            connection_log
        SET
            disconnected_at = :date_time,
            client_lost = 1
        WHERE
            profile_id = :profile_id
        AND
            ip4 = :ip4 
        AND
            ip6 = :ip6 
        AND
            disconnected_at IS NULL
SQL
        );

        $stmt->bindValue(':date_time', $connectedAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_STR);
        $stmt->bindValue(':ip4', $ip4, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->execute();

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
        disconnected_at,
        client_lost
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

    /**
     * @param string $userId
     *
     * @return false|OtpInfo
     */
    public function getOtpSecret($userId)
    {
        $stmt = $this->db->prepare('SELECT otp_secret, otp_hash_algorithm, otp_digits, totp_period FROM otp WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        /** @var false|array<string, string|int> */
        $otpInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $otpInfo) {
            return false;
        }

        return new OtpInfo(
            (string) $otpInfo['otp_secret'],
            (string) $otpInfo['otp_hash_algorithm'],
            (int) $otpInfo['otp_digits'],
            (int) $otpInfo['totp_period']
        );
    }

    /**
     * @param string  $userId
     * @param OtpInfo $otpInfo
     *
     * @return void
     */
    public function setOtpSecret($userId, OtpInfo $otpInfo)
    {
        $stmt = $this->db->prepare('INSERT INTO otp (user_id, otp_secret, otp_hash_algorithm, otp_digits, totp_period) VALUES(:user_id, :otp_secret, :otp_hash_algorithm, :otp_digits, :totp_period)');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':otp_secret', $otpInfo->getSecret(), PDO::PARAM_STR);
        $stmt->bindValue(':otp_hash_algorithm', $otpInfo->getHashAlgorithm(), PDO::PARAM_STR);
        $stmt->bindValue(':otp_digits', $otpInfo->getDigits(), PDO::PARAM_INT);
        $stmt->bindValue(':totp_period', $otpInfo->getPeriod(), PDO::PARAM_INT);

        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return void
     */
    public function deleteOtpSecret($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare('DELETE FROM otp WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return int
     */
    public function getOtpAttemptCount($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM otp_log WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param string    $userId
     * @param string    $otpKey
     * @param \DateTime $dateTime
     *
     * @return bool
     */
    public function recordOtpKey($userId, $otpKey, DateTime $dateTime)
    {
        $this->addUser($userId);
        // check if this user used the key before
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM otp_log WHERE user_id = :user_id AND otp_key = :otp_key');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':otp_key', $otpKey, PDO::PARAM_STR);
        $stmt->execute();
        if (0 !== (int) $stmt->fetchColumn()) {
            return false;
        }

        // because the insert MUST succeed we avoid race condition where
        // potentially two times the same key for the same user are accepted,
        // we'd just get a PDOException because the UNIQUE(user_id, otp_key)
        // constrained is violated
        $stmt = $this->db->prepare('INSERT INTO otp_log (user_id, otp_key, date_time) VALUES (:user_id, :otp_key, :date_time)');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':otp_key', $otpKey, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();

        return true;
    }

    /**
     * @param \DateTime $dateTime
     *
     * @return void
     */
    public function cleanOtpLog(DateTime $dateTime)
    {
        $stmt = $this->db->prepare('DELETE FROM otp_log WHERE date_time < :date_time');
        $stmt->bindValue(':date_time', $dateTime->format('Y-m-d H:i:s'), PDO::PARAM_STR);

        $stmt->execute();
    }

    public function init()
    {
        $this->migration->init();
    }

    /**
     * @return void
     */
    public function update()
    {
        $this->migration->run();
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
            user_id
        )
    VALUES (
        :user_id
    )
SQL
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
}
