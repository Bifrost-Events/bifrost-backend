<?php

declare(strict_types=1);

namespace App\Support;

final class EnvLoader
{
    public static function load(string $basePath): void
    {
        $requested = trim((string) (getenv('BIFROST_DOTENV') ?: ($_SERVER['BIFROST_DOTENV'] ?? '')));
        $envFileName = $requested !== '' ? basename($requested) : '.env';
        $envFile = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $envFileName;

        if (!is_file($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
