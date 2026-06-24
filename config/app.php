<?php

require_once __DIR__ . '/require-env.php';

require_env('APP_ENV');
require_env('APP_DEBUG');
require_env('STORAGE_DRIVER');

return [
    'name' => $_ENV['APP_NAME'] ?? 'Bifrost API',
    'env' => $_ENV['APP_ENV'],
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
];
