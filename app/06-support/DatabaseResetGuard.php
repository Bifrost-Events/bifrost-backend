<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sperrer destruktive database-operasjoner utenfor staging med eksplisitte flagg.
 */
final class DatabaseResetGuard
{
    /** @var list<string> */
    private const BLOCKED_DATABASE_NAMES = [
        'jaktfeltkarusell_prod',
        'bifrost',
    ];

    public static function assertResetAllowed(): void
    {
        if (Environment::isProduction()) {
            throw new \RuntimeException('Database-reset er forbudt når APP_ENV=production.');
        }

        if (Environment::isTest()) {
            throw new \RuntimeException('Database-reset er forbudt når APP_ENV=test.');
        }

        if (!Environment::isStaging()) {
            throw new \RuntimeException('Database-reset er kun tillatt når APP_ENV=staging.');
        }

        if (!Environment::qualityResetDatabaseRequested()) {
            throw new \RuntimeException('Database-reset krever QUALITY_RESET_DATABASE=true.');
        }
    }

    public static function assertSeedAllowed(): void
    {
        if (Environment::isProduction()) {
            throw new \RuntimeException('Database-seed er forbudt når APP_ENV=production.');
        }

        if (Environment::isTest()) {
            throw new \RuntimeException('Database-seed er forbudt når APP_ENV=test.');
        }

        if (!Environment::isStaging()) {
            throw new \RuntimeException('Database-seed er kun tillatt når APP_ENV=staging.');
        }

        if (!Environment::qualitySeedDatabaseRequested()) {
            throw new \RuntimeException('Database-seed krever QUALITY_SEED_DATABASE=true.');
        }
    }

    public static function assertDatabaseNameSafe(string $databaseName): void
    {
        $name = strtolower(trim($databaseName));
        if ($name === '') {
            throw new \RuntimeException('Database-navn er tomt.');
        }

        if (in_array($name, self::BLOCKED_DATABASE_NAMES, true)) {
            throw new \RuntimeException(
                'Sikkerhetsstopp: database "' . $databaseName . '" kan ikke nullstilles via staging-reset.',
            );
        }

        $allowedByName = str_contains($name, 'staging');
        $allowedByFlag = Environment::allowStagingResetOverride();

        if (!$allowedByName && !$allowedByFlag) {
            throw new \RuntimeException(
                'Database-navn må inneholde "staging" eller ALLOW_STAGING_RESET=true må være satt.',
            );
        }
    }
}
