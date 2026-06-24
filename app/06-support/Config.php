<?php

declare(strict_types=1);

namespace App\Support;

class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $file): void
    {
        $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
        if (file_exists($path)) {
            self::$items[$file] = require $path;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = self::$items;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
