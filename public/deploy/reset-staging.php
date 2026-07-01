<?php

declare(strict_types=1);

/**
 * POST /deploy/reset-staging — nullstill staging-database for Playwright (kun APP_ENV=staging).
 */

$basePath = dirname(__DIR__, 2);

require_once $basePath . '/app/06-support/EnvLoader.php';
\App\Support\EnvLoader::load($basePath);

require_once $basePath . '/vendor/autoload.php';

require $basePath . '/app/06-support/bootstrap.php';

use App\Controller\DeployResetController;

$controller = new DeployResetController($basePath);
$response = $controller->handle($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!headers_sent()) {
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $value) {
        header($name . ': ' . $value);
    }
}

echo $response['body'];
