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
use RuntimeException;

class Migrator
{
    /** @var \PDO */
    private $dbh;

    /** @var string */
    private $schemaVersion;

    /** @var array<string, array> */
    private $migrationList = [];

    /**
     * @param \PDO   $dbh
     * @param string $schemaVersion
     */
    public function __construct(PDO $dbh, $schemaVersion)
    {
        $this->dbh = $dbh;
        $this->schemaVersion = $schemaVersion;
    }

    /**
     * @param array<string> $queryList
     *
     * @return void
     */
    public function init(array $queryList)
    {
        $queryList = \array_merge(
            $queryList,
            [
                'CREATE TABLE IF NOT EXISTS version (current_version TEXT NOT NULL)',
                \sprintf('INSERT INTO version (current_version) VALUES("%s")', $this->schemaVersion),
            ]
        );
        foreach ($queryList as $dbQuery) {
            $this->dbh->exec($dbQuery);
        }
    }

    /**
     * @param string        $fromVersion
     * @param string        $toVersion
     * @param array<string> $queryList
     *
     * @return void
     */
    public function addMigration($fromVersion, $toVersion, array $queryList)
    {
        $this->migrationList[\sprintf('%s:%s', $fromVersion, $toVersion)] = $queryList;
    }

    /**
     * @param array<string,array<string>> $dbQueries
     *
     * @return void
     */
    public function update()
    {
        $currentVersion = $this->getCurrentVersion();
        if ($currentVersion === $this->schemaVersion) {
            // database schema is up to date, no update required
            return;
        }

        // make sure we run through the migrations in order
        // :ABC must be before <anything>:ABC XXX
        \ksort($this->migrationList);
        foreach ($this->migrationList as $fromTo => $queryList) {
            list($fromVersion, $toVersion) = \explode(':', $fromTo);
            if ($fromVersion === $currentVersion) {
                try {
                    $this->dbh->beginTransaction();

                    // XXX we really want to delete $fromVersion ONLY, bail if this doesn't work!
                    if (1 !== $this->dbh->exec('DELETE FROM version')) {
                        throw new RuntimeException('XXX');
                    }

                    foreach ($queryList as $dbQuery) {
                        $this->dbh->exec($dbQuery);
                    }
                    $this->dbh->commit();
                    $this->dbh->exec(
                        \sprintf('INSERT INTO version (current_version) VALUES("%s")', $toVersion)
                    );

                    $currentVersion = $toVersion;
                } catch (PDOException $e) {
                    $this->dbh->rollback();

                    throw $e;
                }
            }
        }
    }

    /**
     * @return string
     */
    public function getCurrentVersion()
    {
        try {
            $sth = $this->dbh->query('SELECT current_version FROM version');
            $currentVersion = $sth->fetchColumn(0);
            $sth->closeCursor();
            if (false === $currentVersion) {
                throw new RuntimeException('no version set in the database, but version table exists');
            }

            return $currentVersion;
        } catch (PDOException $e) {
            // create table
            $this->dbh->exec('CREATE TABLE IF NOT EXISTS version (current_version TEXT NOT NULL)');
            $this->dbh->exec('INSERT INTO version (current_version) VALUES("0000000000")');

            return '0000000000';
        }
    }
}
