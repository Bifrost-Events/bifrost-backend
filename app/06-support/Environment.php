<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Applikasjonsmiljøprofiler for Bifrost backend API.
 */
final class Environment
{
    public const STAGING = 'staging';
    public const TEST = 'test';
    public const PRODUCTION = 'production';

    public static function current(): string
    {
        return strtolower(trim((string) ($_ENV['APP_ENV'] ?? self::PRODUCTION)));
    }

    public static function isDevelopment(): bool
    {
        return self::current() === 'development';
    }

    public static function isStaging(): bool
    {
        return self::current() === self::STAGING;
    }

    public static function isTest(): bool
    {
        return self::current() === self::TEST;
    }

    public static function isProduction(): bool
    {
        $env = self::current();

        return $env === self::PRODUCTION || $env === 'prod';
    }

    public static function qualityResetDatabaseRequested(): bool
    {
        return filter_var($_ENV['QUALITY_RESET_DATABASE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public static function qualitySeedDatabaseRequested(): bool
    {
        return filter_var($_ENV['QUALITY_SEED_DATABASE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public static function allowStagingResetOverride(): bool
    {
        return filter_var($_ENV['ALLOW_STAGING_RESET'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public static function stagingDeploySecret(): string
    {
        return trim((string) ($_ENV['STAGING_DEPLOY_SECRET'] ?? ''));
    }

    /**
     * HTTP_HOST må indikere staging (f.eks. staging.api.bifrostevents.no).
     */
    public static function isStagingHttpHost(?string $host = null): bool
    {
        $host = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? ''))));
        if ($host === '') {
            return false;
        }

        return str_contains($host, 'staging');
    }

    public static function safeMessage(\Throwable $e): string
    {
        if (self::isDevelopment() || (($_ENV['APP_DEBUG'] ?? 'false') === 'true')) {
            return $e->getMessage();
        }

        return 'Internal error';
    }
}
