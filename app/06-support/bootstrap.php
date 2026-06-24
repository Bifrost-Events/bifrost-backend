<?php

declare(strict_types=1);

/**
 * @return array{basePath: string}
 */
$basePath = dirname(__DIR__, 2);

$autoload = $basePath . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new RuntimeException('Kjør composer install. Mangler: ' . $autoload);
}
require_once $autoload;

require_once $basePath . '/app/06-support/EnvLoader.php';
\App\Support\EnvLoader::load($basePath);

if (\App\Support\Environment::isDevelopment() || (($_ENV['APP_DEBUG'] ?? 'false') === 'true')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

\App\Support\Config::load('app');
\App\Support\Config::load('services');

return [
    'basePath' => $basePath,
];
