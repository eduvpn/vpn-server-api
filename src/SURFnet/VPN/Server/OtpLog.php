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
