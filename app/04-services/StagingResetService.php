<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Config;
use App\Support\Container;
use App\Support\DatabaseResetGuard;
use App\Support\DeployPathResolver;
use PDO;

/**
 * Nullstiller staging-database, kjører greenfield-migreringer og deterministiske seeds.
 */
final class StagingResetService
{
    /** @var list<string> */
    private const STAGING_SEED_FILES = [
        '001_local_tenants.sql',
        '001_local_greenfield_cup_data.sql',
        '002_local_admin_user.sql',
        '003_quality_local_hosts.sql',
    ];

    public function __construct(
        private readonly string $basePath,
        private readonly SqlScriptRunner $sqlRunner = new SqlScriptRunner(),
    ) {
    }

    /**
     * @return array{status: string, environment: string, message: string, database: string, migrations: int, seeds: int}
     */
    public function resetMigrateAndSeed(): array
    {
        DatabaseResetGuard::assertResetAllowed();
        DatabaseResetGuard::assertSeedAllowed();

        $lockPath = DeployPathResolver::lockFilePath($this->basePath);
        $lockDir = dirname($lockPath);
        if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
            throw new \RuntimeException('Kunne ikke opprette lock-mappe: ' . $lockDir);
        }

        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            throw new \RuntimeException('Kunne ikke åpne staging-reset lock.');
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            throw new \RuntimeException('Staging-reset kjører allerede.');
        }

        try {
            Config::load('services');
            $pdo = (new Container())->getPdo();
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }

            $databaseName = $this->databaseNameFromPdo($pdo);
            DatabaseResetGuard::assertDatabaseNameSafe($databaseName);

            $this->dropAllTables($pdo);
            $migrationCount = $this->runGreenfieldMigrations($pdo);
            $seedCount = $this->runStagingSeeds($pdo);

            return [
                'status' => 'ok',
                'environment' => 'staging',
                'message' => 'Staging database reset, migrated and seeded',
                'database' => $databaseName,
                'migrations' => $migrationCount,
                'seeds' => $seedCount,
            ];
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function databaseNameFromPdo(PDO $pdo): string
    {
        $name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($name === '') {
            throw new \RuntimeException('Kunne ikke lese aktiv database fra PDO.');
        }

        return $name;
    }

    private function dropAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $stmt = $pdo->query('SHOW TABLES');
        if ($stmt === false) {
            throw new \RuntimeException('Kunne ikke liste tabeller.');
        }

        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        foreach ($tables as $table) {
            $escaped = str_replace('`', '``', (string) $table);
            $pdo->exec('DROP TABLE IF EXISTS `' . $escaped . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function runGreenfieldMigrations(PDO $pdo): int
    {
        $migrationsDir = DeployPathResolver::migrationsPath();

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(255) PRIMARY KEY,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $files = glob($migrationsDir . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);

        $applied = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (!$this->isGreenfieldMigration($name)) {
                continue;
            }

            $this->sqlRunner->runFile($pdo, $file);

            $insert = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
            $insert->execute([$name]);
            $insert->closeCursor();
            $applied++;
        }

        return $applied;
    }

    private function isGreenfieldMigration(string $name): bool
    {
        if ($name === '001_initial_bifrost_schema.sql') {
            return true;
        }

        if (str_starts_with($name, 'auth_')) {
            return true;
        }

        if (str_starts_with($name, 'bifrost_') && !str_contains($name, 'backfill')) {
            return true;
        }

        return false;
    }

    private function runStagingSeeds(PDO $pdo): int
    {
        $seedsDir = DeployPathResolver::seedsPath();
        $count = 0;

        foreach (self::STAGING_SEED_FILES as $file) {
            $path = $seedsDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                throw new \RuntimeException('Mangler seed-fil: ' . $file);
            }

            $this->sqlRunner->runFile($pdo, $path);
            $count++;
        }

        return $count;
    }
}
