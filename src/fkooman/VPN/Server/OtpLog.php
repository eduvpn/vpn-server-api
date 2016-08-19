<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

use PDO;
use PDOException;

/**
 * Keep track of the used OTP keys so they cannot be replayed.
 */
class OtpLog
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $prefix;

    public function __construct(PDO $db, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function record($userId, $otpKey, $timeUnix)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (
                    user_id,
                    otp_key,
                    time_unix
                 ) 
                 VALUES(
                    :user_id, 
                    :otp_key,
                    :time_unix
                 )',
                $this->prefix.'otp_log'
            )
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':otp_key', $otpKey, PDO::PARAM_STR);
        $stmt->bindValue(':time_unix', $timeUnix, PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        return true;
    }

    public function housekeeping($timeUnix)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s 
                    WHERE time_unix < :time_unix',
                $this->prefix.'otp_log'
            )
        );

        $stmt->bindValue(':time_unix', $timeUnix, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function createTableQueries($prefix)
    {
        $query = array(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    user_id VARCHAR(255) NOT NULL,
                    otp_key VARCHAR(255) NOT NULL,
                    time_unix INTEGER NOT NULL,
                    UNIQUE(user_id, otp_key)
                )',
                $prefix.'otp_log'
            ),
        );

        return $query;
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }
    }
}
