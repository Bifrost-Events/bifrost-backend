<?php

declare(strict_types=1);

namespace App\Support;

final class Environment
{
    public static function current(): string
    {
        return strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'production')));
    }

    public static function isDevelopment(): bool
    {
        return self::current() === 'development';
    }

    public static function safeMessage(\Throwable $e): string
    {
        if (self::isDevelopment() || (($_ENV['APP_DEBUG'] ?? 'false') === 'true')) {
            return $e->getMessage();
        }

        return 'Internal error';
    }
}
