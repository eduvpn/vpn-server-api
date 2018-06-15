<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server;

use PDO;
use PDOException;
use RangeException;

class Migrator
{
    const NO_VERSION = '0000000000';

    /** @var \PDO */
    private $dbh;

    /** @var string */
    private $schemaVersion;

    /** @var array<string, array> */
    private $updateList = [];

    /**
     * @param \PDO   $dbh
     * @param string $schemaVersion
     */
    public function __construct(PDO $dbh, $schemaVersion)
    {
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->dbh = $dbh;
        if (1 !== \preg_match('/^[0-9]{10}$/', $schemaVersion)) {
            throw new RangeException('schemaVersion must be 10 a digit string');
        }
        $this->schemaVersion = $schemaVersion;
    }

    /**
     * @param array $queryList
     *
     * @return void
     */
    public function init(array $queryList = [])
    {
        foreach ($queryList as $dbQuery) {
            $this->dbh->exec($dbQuery);
        }
        $this->createVersionTable($this->schemaVersion);
    }

    /**
     * @return bool
     */
    public function isUpdateRequired()
    {
        return $this->schemaVersion !== $this->getCurrentVersion();
    }

    /**
     * @param string        $fromVersion
     * @param string        $toVersion
     * @param array<string> $queryList
     *
     * @return void
     */
    public function addUpdate($fromVersion, $toVersion, array $queryList)
    {
        $fromToVersion = \sprintf('%s:%s', $fromVersion, $toVersion);
        if (1 !== \preg_match('/^[0-9]{10}:[0-9]{10}$/', $fromToVersion)) {
            throw new RangeException('fromVersion and toVersion must be 10 digit strings');
        }
        $this->updateList[$fromToVersion] = $queryList;
    }

    /**
     * @return void
     */
    public function update()
    {
        $currentVersion = $this->getCurrentVersion();
        if ($currentVersion === $this->schemaVersion) {
            // database schema is up to date, no update required
            return;
        }

        $this->dbh->exec('CREATE TABLE _migration_in_progress (dummy INTEGER)');

        // make sure we run through the migrations in order
        \ksort($this->updateList);
        foreach ($this->updateList as $fromTo => $queryList) {
            list($fromVersion, $toVersion) = \explode(':', $fromTo);
            if ($fromVersion === $currentVersion) {
                try {
                    $this->dbh->beginTransaction();
                    $this->dbh->exec(\sprintf("DELETE FROM version WHERE current_version = '%s'", $fromVersion));
                    foreach ($queryList as $dbQuery) {
                        $this->dbh->exec($dbQuery);
                    }
                    $this->dbh->exec(\sprintf("INSERT INTO version (current_version) VALUES('%s')", $toVersion));
                    $this->dbh->commit();
                    $currentVersion = $toVersion;
                } catch (PDOException $e) {
                    $this->dbh->rollback();

                    throw $e;
                }
            }
        }

        $this->dbh->exec('DROP TABLE _migration_in_progress');
    }

    /**
     * @return false|string
     */
    public function getCurrentVersion()
    {
        try {
            $sth = $this->dbh->query('SELECT current_version FROM version');
            $currentVersion = $sth->fetchColumn(0);
            $sth->closeCursor();

            return $currentVersion;
        } catch (PDOException $e) {
            $this->createVersionTable(self::NO_VERSION);

            return self::NO_VERSION;
        }
    }

    /**
     * @param string $schemaVersion
     *
     * @return void
     */
    private function createVersionTable($schemaVersion)
    {
        $this->dbh->exec('CREATE TABLE IF NOT EXISTS version (current_version TEXT NOT NULL)');
        $this->dbh->exec(\sprintf("INSERT INTO version (current_version) VALUES('%s')", $schemaVersion));
    }
}
