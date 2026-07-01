<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Finner migrerings- og seed-mapper på server (deploy bundle eller bifrost-shared sibling).
 */
final class DeployPathResolver
{
    public static function migrationsPath(): string
    {
        return self::resolveExistingPath(
            'MIGRATIONS_PATH',
            [
                '/database/migrations',
                '/../bifrost-shared/database/migrations',
            ],
        );
    }

    public static function seedsPath(): string
    {
        return self::resolveExistingPath(
            'SEEDS_PATH',
            [
                '/database/seeds',
                '/../bifrost-shared/database/seeds',
            ],
        );
    }

    public static function lockFilePath(string $basePath): string
    {
        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'staging-reset.lock';
    }

    /**
     * @param list<string> $relativeCandidates paths relative to project base
     */
    private static function resolveExistingPath(string $envKey, array $relativeCandidates): string
    {
        $configured = trim((string) ($_ENV[$envKey] ?? ''));
        if ($configured !== '') {
            $resolved = realpath($configured);
            if ($resolved === false || !is_dir($resolved)) {
                throw new \RuntimeException("Konfigurert {$envKey} finnes ikke: {$configured}");
            }

            return $resolved;
        }

        $basePath = dirname(__DIR__, 2);
        foreach ($relativeCandidates as $relative) {
            $candidate = realpath($basePath . $relative);
            if ($candidate !== false && is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Fant ingen mappe for {$envKey}. Deploy database/migrations eller database/seeds.");
    }
}
