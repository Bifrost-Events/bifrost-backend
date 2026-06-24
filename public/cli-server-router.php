<?php

declare(strict_types=1);

$docRoot = realpath(__DIR__);
if ($docRoot === false) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
    return;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$path = str_replace('\\', '/', rawurldecode($path));
if ($path === '' || $path[0] !== '/') {
    $path = '/' . ltrim($path, '/');
}
if (str_contains($path, '..')) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
    return;
}

$candidate = realpath($docRoot . $path);
if ($candidate !== false && str_starts_with($candidate, $docRoot) && is_file($candidate)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
