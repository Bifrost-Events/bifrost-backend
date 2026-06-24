<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

require_once $basePath . '/app/06-support/EnvLoader.php';
\App\Support\EnvLoader::load($basePath);

$renderError = function (\Throwable $e): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $showErrors = ($_ENV['APP_ENV'] ?? 'production') === 'development'
        || ($_ENV['APP_DEBUG'] ?? 'false') === 'true'
        || isset($_GET['debug']);
    $payload = ['error' => 'Server error'];
    if ($showErrors) {
        $payload['message'] = $e->getMessage();
        $payload['file'] = $e->getFile();
        $payload['line'] = $e->getLine();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};
set_exception_handler($renderError);
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
}, E_ALL & ~E_DEPRECATED & ~E_STRICT);

try {
    $app = require $basePath . '/app/06-support/bootstrap.php';
} catch (\Throwable $e) {
    $renderError($e);
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$scriptDir = dirname($scriptName);
if ($scriptDir !== '.' && $scriptDir !== '\\' && $scriptDir !== '/') {
    $scriptDir = '/' . ltrim(str_replace('\\', '/', $scriptDir), '/');
    if (str_starts_with($requestUri, $scriptDir)) {
        $requestUri = substr($requestUri, strlen($scriptDir)) ?: '/';
    }
}
$requestUri = '/' . ltrim($requestUri, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (str_starts_with($requestUri, '/api/auth') || str_starts_with($requestUri, '/api/admin')) {
    \App\Support\Session::startRequired();
}

$router = (require $basePath . '/routes/web.php')($app);

try {
    $response = $router->dispatch($method, $requestUri);
} catch (\Throwable $e) {
    $renderError($e);
}

if (!headers_sent()) {
    http_response_code($response['status']);
    foreach ($response['headers'] as $name => $val) {
        header("$name: $val");
    }
}
echo $response['body'];
