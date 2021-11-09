<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server;

use DateTime;
use fkooman\Otp\OtpInfo;
use fkooman\Otp\OtpStorageInterface;
use fkooman\SqliteMigrate\Migration;
use LC\Common\Json;
use PDO;

class Storage implements OtpStorageInterface
{
    const CURRENT_SCHEMA_VERSION = '2019032701';

    /** @var \PDO */
    private $db;

    /** @var \fkooman\SqliteMigrate\Migration */
    private $migration;

    /** @var \DateTime */
    private $dateTime;

    /**
     * @param string $schemaDir
     */
    public function __construct(PDO $db, $schemaDir)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
        $this->db = $db;
        $this->migration = new Migration($db, $schemaDir, self::CURRENT_SCHEMA_VERSION);
        $this->dateTime = new DateTime();
    }

    /**
     * @return void
     */
    public function setDateTime(DateTime $dateTime)
    {
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
        session_expires_at,
        permission_list,
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
                'has_totp_secret' => null !== $row['otp_secret'],
                'session_expires_at' => $row['session_expires_at'],
                'permission_list' => Json::decode($row['permission_list']),
            ];
        }

        return $userList;
    }

    /**
     * @param string $userId
     *
     * @return scalar|null
     */
    public function getSessionExpiresAt($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        session_expires_at
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

    /**
     * @param string $userId
     *
     * @return array<string>
     */
    public function getPermissionList($userId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        permission_list
    FROM
        users
    WHERE
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return Json::decode($stmt->fetchColumn());
    }

    /**
     * @return array
     */
    public function getAppUsage()
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        client_id,
        COUNT(DISTINCT user_id) AS client_count
    FROM
        certificates
    GROUP BY
        client_id
    ORDER BY
        client_count DESC
SQL
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $commonName
     *
     * @return false|array
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
        c.valid_to,
        c.client_id
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
     * @return void
     */
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

    /**
     * @param string        $userId
     * @param array<string> $permissionList
     *
     * @return void
     */
    public function updateSessionInfo($userId, DateTime $sessionExpiresAt, array $permissionList)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    UPDATE
        users
    SET
        session_expires_at = :session_expires_at,
        permission_list = :permission_list
    WHERE
        user_id = :user_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':session_expires_at', $sessionExpiresAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':permission_list', Json::encode($permissionList), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @param string      $userId
     * @param string      $commonName
     * @param string      $displayName
     * @param string|null $clientId
     *
     * @return void
     */
    public function addCertificate($userId, $commonName, $displayName, DateTime $validFrom, DateTime $validTo, $clientId)
    {
        $this->addUser($userId);
        $stmt = $this->db->prepare(
<<< 'SQL'
    INSERT INTO certificates
        (common_name, user_id, display_name, valid_from, valid_to, client_id)
    VALUES
        (:common_name, :user_id, :display_name, :valid_from, :valid_to, :client_id)
SQL
        );
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':valid_from', $validFrom->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':valid_to', $validTo->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR | PDO::PARAM_NULL);
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
        client_id
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

    /**
     * @param string $commonName
     *
     * @return void
     */
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

    /**
     * @param string $userId
     * @param string $clientId
     *
     * @return void
     */
    public function deleteCertificatesOfClientId($userId, $clientId)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    DELETE FROM
        certificates
    WHERE
        user_id = :user_id
    AND
        client_id = :client_id
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return void
     */
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

    /**
     * @param string $userId
     *
     * @return void
     */
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

        // because the user always exists, this will always return something,
        // this is why we don't need to distinguish between a successful fetch
        // or not, a bit ugly!
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param string $profileId
     * @param string $commonName
     * @param string $ip4
     * @param string $ip6
     *
     * @return void
     */
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

        $stmt->bindValue(':date_time', $connectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
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
        $stmt->bindValue(':connected_at', $connectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $profileId
     * @param string $commonName
     * @param string $ip4
     * @param string $ip6
     * @param int    $bytesTransferred
     *
     * @return void
     */
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
        $stmt->bindValue(':connected_at', $connectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':disconnected_at', $disconnectedAt->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->bindValue(':bytes_transferred', $bytesTransferred, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return array
     */
    public function getConnectionLogForUser($userId)
    {
        $stmt = $this->db->prepare(
<<< 'SQL'
    SELECT
        l.user_id,
        l.common_name,
        l.profile_id,
        l.ip4,
        l.ip6,
        l.connected_at,
        l.disconnected_at,
        l.bytes_transferred,
        l.client_lost,
        c.client_id AS client_id
    FROM
        connection_log l,
        certificates c
    WHERE
        l.user_id = :user_id
    AND
        l.common_name = c.common_name
    ORDER BY
        l.connected_at
    DESC
SQL
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $ipAddress
     *
     * @return false|array
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
        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();

        // XXX can this also contain multiple results? I don't think so, but
        // make sure!
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return void
     */
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

        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @return void
     */
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

        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);

        $stmt->execute();
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

    /**
     * @param string $type
     * @param string $message
     *
     * @return void
     */
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
        $stmt->bindValue(':date_time', $this->dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param int $messageId
     *
     * @return void
     */
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

    /**
     * @param string $userId
     * @param string $type
     * @param string $message
     *
     * @return void
     */
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
        $stmt->bindValue(':date_time', $this->dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param string $userId
     *
     * @return false|\fkooman\Otp\OtpInfo
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
     * @param string $userId
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
     * @param string $userId
     * @param string $totpKey
     *
     * @return bool
     */
    public function recordOtpKey($userId, $totpKey, DateTime $dateTime)
    {
        $this->addUser($userId);
        // check if this user used the key before
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM otp_log WHERE user_id = :user_id AND otp_key = :otp_key');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':otp_key', $totpKey, PDO::PARAM_STR);
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
        $stmt->bindValue(':otp_key', $totpKey, PDO::PARAM_STR);
        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
        $stmt->execute();

        return true;
    }

    /**
     * @return void
     */
    public function cleanExpiredCertificates(DateTime $dateTime)
    {
        $stmt = $this->db->prepare('DELETE FROM certificates WHERE valid_to < :date_time');
        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @return void
     */
    public function cleanOtpLog(DateTime $dateTime)
    {
        $stmt = $this->db->prepare('DELETE FROM otp_log WHERE date_time < :date_time');
        $stmt->bindValue(':date_time', $dateTime->format(DateTime::ATOM), PDO::PARAM_STR);

        $stmt->execute();
    }

    /**
     * @return void
     */
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

    /**
     * @return \PDO
     */
    public function getPdo()
    {
        return $this->db;
    }

    /**
     * @param string $userId
     *
     * @return void
     */
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
            session_expires_at,
            permission_list,
            is_disabled
        )
    VALUES (
        :user_id,
        :session_expires_at,
        :permission_list,
        :is_disabled
    )
SQL
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':session_expires_at', $this->dateTime->format(DateTime::ATOM), PDO::PARAM_STR);
            $stmt->bindValue(':permission_list', '[]', PDO::PARAM_STR);
            $stmt->bindValue(':is_disabled', false, PDO::PARAM_BOOL);
            $stmt->execute();
        }
    }
}
