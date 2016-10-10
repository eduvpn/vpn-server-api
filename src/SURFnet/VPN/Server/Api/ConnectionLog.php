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
namespace SURFnet\VPN\Server\Api;

use PDO;
use PDOException;

/**
 * Keep track of VPN connections.
 */
class ConnectionLog
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
    }

    public function connect($poolId, $commonName, $ip, $ip6, $connectedAt)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO connection_log (
                pool_id,
                common_name,
                ip,
                ip6,
                connected_at
             ) 
             VALUES(
                :pool_id, 
                :common_name,
                :ip,
                :ip6,
                :connected_at
             )'
        );

        $stmt->bindValue(':pool_id', $poolId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt, PDO::PARAM_INT);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        return true;
    }

    public function disconnect($poolId, $commonName, $ip, $ip6, $connectedAt, $disconnectedAt, $bytesTransferred)
    {
        $stmt = $this->db->prepare(
            'UPDATE connection_log
                SET 
                    disconnected_at = :disconnected_at, 
                    bytes_transferred = :bytes_transferred
                WHERE 
                    pool_id = :pool_id AND
                    common_name = :common_name AND
                    ip = :ip AND
                    ip6 = :ip6 AND
                    connected_at = :connected_at
            '
        );

        $stmt->bindValue(':pool_id', $poolId, PDO::PARAM_STR);
        $stmt->bindValue(':common_name', $commonName, PDO::PARAM_STR);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':ip6', $ip6, PDO::PARAM_STR);
        $stmt->bindValue(':connected_at', $connectedAt, PDO::PARAM_INT);
        $stmt->bindValue(':disconnected_at', $disconnectedAt, PDO::PARAM_INT);
        $stmt->bindValue(':bytes_transferred', $bytesTransferred, PDO::PARAM_INT);

        // XXX number of affected rows should be one!
        try {
            $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }

        return true;
    }

    public function init()
    {
        $queryList = [
            'CREATE TABLE IF NOT EXISTS connection_log (
                pool_id VARCHAR(255) NOT NULL,
                common_name VARCHAR(255) NOT NULL,
                ip VARCHAR(255) NOT NULL,
                ip6 VARCHAR(255) NOT NULL,
                connected_at INTEGER NOT NULL,
                disconnected_at INTEGER DEFAULT NULL,
                bytes_transferred INTEGER DEFAULT NULL                
            )',
        ];

        foreach ($queryList as $query) {
            $this->db->query($query);
        }
    }
}
