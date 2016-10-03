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

    public function __construct(PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
    }

    public function record($userId, $otpKey, $timeUnix)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO otp_log (
                user_id,
                otp_key,
                time_unix
             ) 
             VALUES(
                :user_id, 
                :otp_key,
                :time_unix
             )'
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
                'DELETE FROM otp_log
                    WHERE time_unix < :time_unix'
            )
        );

        $stmt->bindValue(':time_unix', $timeUnix, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function init()
    {
        $queryList = [
            'CREATE TABLE IF NOT EXISTS otp_log (
                user_id VARCHAR(255) NOT NULL,
                otp_key VARCHAR(255) NOT NULL,
                time_unix INTEGER NOT NULL,
                UNIQUE(user_id, otp_key)
            )',
        ];

        foreach ($queryList as $query) {
            $this->db->query($query);
        }
    }
}
