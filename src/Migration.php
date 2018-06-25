<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server;

use Exception;
use PDO;
use PDOException;
use RangeException;
use RuntimeException;
use SURFnet\VPN\Server\Exception\MigrationException;

class Migration
{
    const NO_VERSION = '0000000000';

    /** @var \PDO */
    private $dbh;

    /** @var string */
    private $schemaVersion;

    /** @var string */
    private $schemaDir;

    /**
     * @param \PDO   $dbh           database handle
     * @param string $schemaDir     directory containing schema and migration files
     * @param string $schemaVersion most recent database schema version
     */
    public function __construct(PDO $dbh, $schemaDir, $schemaVersion)
    {
        if ('sqlite' !== $dbh->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            // we only support SQLite for now
            throw new RuntimeException('only SQLite is supported');
        }
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->dbh = $dbh;
        $this->schemaDir = $schemaDir;
        $this->schemaVersion = self::validateSchemaVersion($schemaVersion);
    }

    /**
     * Initialize the database using the schema file located in the schema
     * directory with schema version.
     *
     * @return void
     */
    public function init()
    {
        $this->runQueries(
            self::getQueriesFromFile(\sprintf('%s/%s.schema', $this->schemaDir, $this->schemaVersion))
        );
        $this->createVersionTable($this->schemaVersion);
    }

    /**
     * Run the migration.
     *
     * @return bool
     */
    public function run()
    {
        $currentVersion = $this->getCurrentVersion();
        if ($currentVersion === $this->schemaVersion) {
            // database schema is up to date, no update required
            return false;
        }

        $migrationList = @\glob(\sprintf('%s/*_*.migration', $this->schemaDir));
        if (false === $migrationList) {
            throw new RuntimeException(\sprintf('unable to read schema directory "%s"', $this->schemaDir));
        }

        $hasForeignKeys = $this->lock();

        try {
            foreach ($migrationList as $migrationFile) {
                $migrationVersion = \basename($migrationFile, '.migration');
                list($fromVersion, $toVersion) = self::validateMigrationVersion($migrationVersion);
                if ($fromVersion === $currentVersion && $fromVersion !== $this->schemaVersion) {
                    // get the queries before we start the transaction as we
                    // ONLY want to deal with "PDOExceptions" once the
                    // transacation started...
                    $queryList = self::getQueriesFromFile(\sprintf('%s/%s.migration', $this->schemaDir, $migrationVersion));
                    try {
                        $this->dbh->beginTransaction();
                        $this->dbh->exec(\sprintf("DELETE FROM version WHERE current_version = '%s'", $fromVersion));
                        $this->runQueries($queryList);
                        $this->dbh->exec(\sprintf("INSERT INTO version (current_version) VALUES('%s')", $toVersion));
                        $this->dbh->commit();
                        $currentVersion = $toVersion;
                    } catch (PDOException $e) {
                        // something went wrong with the SQL queries
                        $this->dbh->rollback();

                        throw $e;
                    }
                }
            }
        } catch (Exception $e) {
            // something went wrong that was not related to SQL queries
            $this->unlock($hasForeignKeys);

            throw $e;
        }

        $this->unlock($hasForeignKeys);

        $currentVersion = $this->getCurrentVersion();
        if ($currentVersion !== $this->schemaVersion) {
            throw new MigrationException(
                \sprintf('unable to upgrade to "%s", not all required migrations are available', $this->schemaVersion)
            );
        }

        return true;
    }

    /**
     * Gets the current version of the database schema.
     *
     * @return string
     */
    public function getCurrentVersion()
    {
        try {
            $sth = $this->dbh->query('SELECT current_version FROM version');
            $currentVersion = $sth->fetchColumn(0);
            $sth->closeCursor();
            if (false === $currentVersion) {
                throw new MigrationException('unable to retrieve current version');
            }

            return $currentVersion;
        } catch (PDOException $e) {
            $this->createVersionTable(self::NO_VERSION);

            return self::NO_VERSION;
        }
    }

    /**
     * @return bool
     */
    private function lock()
    {
        // this creates a "lock" as only one process will succeed in this...
        $this->dbh->exec('CREATE TABLE _migration_in_progress (dummy INTEGER)');

        if ($hasForeignKeys = $this->hasForeignKeys()) {
            $this->dbh->exec('PRAGMA foreign_keys = OFF');
        }

        return $hasForeignKeys;
    }

    /**
     * @param bool $hasForeignKeys
     *
     * @return void
     */
    private function unlock($hasForeignKeys)
    {
        // enable "foreign_keys" if they were on...
        if ($hasForeignKeys) {
            $this->dbh->exec('PRAGMA foreign_keys = ON');
        }
        // release "lock"
        $this->dbh->exec('DROP TABLE _migration_in_progress');
    }

    /**
     * @param string $schemaVersion
     *
     * @return void
     */
    private function createVersionTable($schemaVersion)
    {
        $this->dbh->exec('CREATE TABLE version (current_version TEXT NOT NULL)');
        $this->dbh->exec(\sprintf("INSERT INTO version (current_version) VALUES('%s')", $schemaVersion));
    }

    /**
     * @param array<string> $queryList
     *
     * @return void
     */
    private function runQueries(array $queryList)
    {
        foreach ($queryList as $dbQuery) {
            if (0 !== \strlen(\trim($dbQuery))) {
                $this->dbh->exec($dbQuery);
            }
        }
    }

    /**
     * @return bool
     */
    private function hasForeignKeys()
    {
        $sth = $this->dbh->query('PRAGMA foreign_keys');
        $hasForeignKeys = '1' === $sth->fetchColumn(0);
        $sth->closeCursor();

        return $hasForeignKeys;
    }

    /**
     * @param string $filePath
     *
     * @return array<string>
     */
    private static function getQueriesFromFile($filePath)
    {
        $fileContent = @\file_get_contents($filePath);
        if (false === $fileContent) {
            throw new RuntimeException(\sprintf('unable to read "%s"', $filePath));
        }

        return \explode(';', $fileContent);
    }

    /**
     * @param string $schemaVersion
     *
     * @return string
     */
    private static function validateSchemaVersion($schemaVersion)
    {
        if (1 !== \preg_match('/^[0-9]{10}$/', $schemaVersion)) {
            throw new RangeException('schemaVersion must be 10 a digit string');
        }

        return $schemaVersion;
    }

    /**
     * @param string $migrationVersion
     *
     * @return array<string>
     */
    private static function validateMigrationVersion($migrationVersion)
    {
        if (1 !== \preg_match('/^[0-9]{10}_[0-9]{10}$/', $migrationVersion)) {
            throw new RangeException('migrationVersion must be two times a 10 digit string separated by an underscore');
        }

        return \explode('_', $migrationVersion);
    }
}
