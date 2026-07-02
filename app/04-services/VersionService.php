<?php

declare(strict_types=1);

namespace App\Service;

final class VersionService
{
    /**
     * Read-only release metadata. Ingen hemmeligheter.
     *
     * @return array{service: string, releaseId: ?string, commit: ?string, repo: ?string, builtAt: ?string}
     */
    public static function metadata(?string $basePath = null): array
    {
        $basePath = $basePath ?? dirname(__DIR__, 2);
        $file = $basePath . '/public/version.json';

        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                return [
                    'service' => 'bifrost-backend',
                    'releaseId' => isset($decoded['releaseId']) ? (string) $decoded['releaseId'] : null,
                    'commit' => isset($decoded['commit']) ? (string) $decoded['commit'] : null,
                    'repo' => isset($decoded['repo']) ? (string) $decoded['repo'] : null,
                    'builtAt' => isset($decoded['builtAt']) ? (string) $decoded['builtAt'] : null,
                ];
            }
        }

        return [
            'service' => 'bifrost-backend',
            'releaseId' => self::envString('RELEASE_ID'),
            'commit' => self::envString('GIT_COMMIT'),
            'repo' => self::envString('GIT_REPO'),
            'builtAt' => null,
        ];
    }

    private static function envString(string $key): ?string
    {
        $value = trim((string) ($_ENV[$key] ?? ''));
        return $value !== '' ? $value : null;
    }
}
