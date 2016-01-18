<?php

namespace fkooman\VPN\Server;

use PDO;
use RuntimeException;
use fkooman\IO\IO;

/**
 * With this we store events from client-connect and client-disconnect
 * from the OpenVPN server.
 */
class ConnectionLog
{
    /** @var PDO */
    private $db;

    /** @var \fkooman\IO\IO */
    private $io;

    /** @var string */
    private $prefix;

    public function __construct(PDO $db, IO $io = null, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        if (is_null($io)) {
            $io = new IO();
        }
        $this->io = $io;
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
                    ifconfig_pool_remote_ip,
                    ifconfig_ipv6_remote
                 ) 
                 VALUES(
                    :common_name, 
                    :time_unix, 
                    :ifconfig_pool_remote_ip, 
                    :ifconfig_ipv6_remote
                 )',
                $this->prefix.'connections'
            )
        );

        $stmt->bindValue(':common_name', $v['common_name'], PDO::PARAM_STR);
        $stmt->bindValue(':time_unix', intval($v['time_unix']), PDO::PARAM_INT);
        $stmt->bindValue(':ifconfig_pool_remote_ip', $v['ifconfig_pool_remote_ip'], PDO::PARAM_STR);
        $stmt->bindValue(':ifconfig_ipv6_remote', $v['ifconfig_ipv6_remote'], PDO::PARAM_STR);
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
                        AND ifconfig_pool_remote_ip = :ifconfig_pool_remote_ip
                        AND ifconfig_ipv6_remote = :ifconfig_ipv6_remote',
                $this->prefix.'connections'
            )
        );

        $stmt->bindValue(':common_name', $v['common_name'], PDO::PARAM_STR);
        $stmt->bindValue(':time_unix', intval($v['time_unix']), PDO::PARAM_INT);
        $stmt->bindValue(':ifconfig_pool_remote_ip', $v['ifconfig_pool_remote_ip'], PDO::PARAM_STR);
        $stmt->bindValue(':ifconfig_ipv6_remote', $v['ifconfig_ipv6_remote'], PDO::PARAM_STR);
        $stmt->bindValue(':bytes_received', intval($v['bytes_received']), PDO::PARAM_INT);
        $stmt->bindValue(':bytes_sent', intval($v['bytes_sent']), PDO::PARAM_INT);
        $stmt->bindValue(':disconnect_time_unix', $this->io->getTime(), PDO::PARAM_INT);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getConnectionHistory()
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT * 
                 FROM %s 
                 WHERE 
                    disconnect_time_unix NOT NULL 
                 ORDER BY disconnect_time_unix DESC',
                $this->prefix.'connections'
            )
        );
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
                    ifconfig_pool_remote_ip VARCHAR(255) NOT NULL,
                    ifconfig_ipv6_remote VARCHAR(255) NOT NULL,
                    bytes_received INTEGER DEFAULT NULL,
                    bytes_sent INTEGER DEFAULT NULL,
                    disconnect_time_unix INTEGER DEFAULT NULL
                )',
                $prefix.'connections'
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

        $tables = array('connections');
        foreach ($tables as $t) {
            // make sure the tables are empty
            $this->db->query(
                sprintf(
                    'DELETE FROM %s',
                    $this->prefix.$t
                )
            );
        }
    }
}
