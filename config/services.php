<?php

require_once __DIR__ . '/require-env.php';

require_env('STORAGE_DRIVER');

$storageDriver = strtolower(trim((string) $_ENV['STORAGE_DRIVER']));
if ($storageDriver === 'pdo') {
    require_env('DB_DSN');
    require_env('DB_USER');
    if (!isset($_ENV['DB_PASS'])) {
        $_ENV['DB_PASS'] = '';
    }
}

return [
    'storage_driver' => $storageDriver,
    'migrations_path' => $_ENV['MIGRATIONS_PATH']
        ?? dirname(__DIR__) . '/database/migrations',
    'seeds_path' => $_ENV['SEEDS_PATH']
        ?? dirname(__DIR__) . '/database/seeds',
];
