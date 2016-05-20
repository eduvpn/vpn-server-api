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

namespace fkooman\VPN\Server\Log;

use PDO;
use RuntimeException;

/**
 * With this we store events from client-connect and client-disconnect
 * from the OpenVPN server.
 */
class ConnectionLog
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

    /**
     * Handle connecting client.
     *
     * @param array $v the environment variables from the connect event
     *
     * @throws RuntimeException if the insert fails
     */
    public function connect(array $v)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (
                    common_name,
                    time_unix,
                    v4,
                    v6
                 ) 
                 VALUES(
                    :common_name, 
                    :time_unix, 
                    :v4, 
                    :v6
                 )',
                $this->prefix.'connections'
            )
        );

        $stmt->bindValue(':common_name', $v['common_name'], PDO::PARAM_STR);
        $stmt->bindValue(':time_unix', intval($v['time_unix']), PDO::PARAM_INT);
        $stmt->bindValue(':v4', $v['v4'], PDO::PARAM_STR);
        $stmt->bindValue(':v6', $v['v6'], PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new RuntimeException('unable to insert');
        }
    }

    /**
     * Handle disconnecting client.
     *
     * @param array $v the environment variables from the disconnect event
     *
     * @return bool whether the update succeeded, returns false if there is no
     *              matching 'connect' in the database
     */
    public function disconnect(array $v)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE %s 
                    SET bytes_received = :bytes_received, bytes_sent = :bytes_sent, disconnect_time_unix = :disconnect_time_unix
                    WHERE common_name = :common_name 
                        AND time_unix = :time_unix
                        AND v4 = :v4
                        AND v6 = :v6',
                $this->prefix.'connections'
            )
        );

        $stmt->bindValue(':common_name', $v['common_name'], PDO::PARAM_STR);
        $stmt->bindValue(':time_unix', intval($v['time_unix']), PDO::PARAM_INT);
        $stmt->bindValue(':v4', $v['v4'], PDO::PARAM_STR);
        $stmt->bindValue(':v6', $v['v6'], PDO::PARAM_STR);
        $stmt->bindValue(':bytes_received', intval($v['bytes_received']), PDO::PARAM_INT);
        $stmt->bindValue(':bytes_sent', intval($v['bytes_sent']), PDO::PARAM_INT);
        $stmt->bindValue(':disconnect_time_unix', intval($v['disconnect_time_unix']), PDO::PARAM_INT);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getConnectionHistory($minUnix, $maxUnix)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT * 
                 FROM %s 
                 WHERE 
                    disconnect_time_unix NOT NULL
                 AND
                    disconnect_time_unix >= :min_unix
                 AND
                    disconnect_time_unix <= :max_unix
                 ORDER BY disconnect_time_unix DESC',
                $this->prefix.'connections'
            )
        );
        $stmt->bindValue(':min_unix', $minUnix, PDO::PARAM_INT);
        $stmt->bindValue(':max_unix', $maxUnix, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createTableQueries($prefix)
    {
        $query = array(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    common_name VARCHAR(255) NOT NULL,
                    time_unix INTEGER NOT NULL,
                    v4 VARCHAR(255) NOT NULL,
                    v6 VARCHAR(255) NOT NULL,
                    bytes_received INTEGER DEFAULT NULL,
                    bytes_sent INTEGER DEFAULT NULL,
                    disconnect_time_unix INTEGER DEFAULT NULL
                )',
                $prefix.'connections'
            ),
        );

        return $query;
    }

    /**
     * Remove all log entries older than provided timestamp.
     *
     * @param int $timeStamp the unix timestamp before which to remove all log entries
     */
    public function housekeeping($timeStamp)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s 
                    WHERE disconnect_time_unix < :time_stamp',
                $this->prefix.'connections'
            )
        );

        $stmt->bindValue(':time_stamp', $timeStamp, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }
    }
}
