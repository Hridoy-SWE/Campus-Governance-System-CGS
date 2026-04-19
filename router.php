<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uri === '/index.php') {
    $uri = '/';
}

if (str_starts_with($uri, '/frontend/frontend/')) {
    $target = preg_replace('#^/frontend/frontend/#', '/frontend/', $uri);
    header('Location: ' . $target, true, 302);
    exit;
}

if ($uri === '/' || $uri === '') {
    header('Location: /frontend/index.html', true, 302);
    exit;
}

if (str_starts_with($uri, '/api/') || $uri === '/api' || $uri === '/health') {
    require __DIR__ . '/api/index.php';
    return;
}

$path = realpath(__DIR__ . $uri);
if ($path && is_file($path) && str_starts_with($path, realpath(__DIR__))) {
    return false;
}

if ($uri === '/frontend' || $uri === '/frontend/') {
    header('Location: /frontend/index.html', true, 302);
    exit;
}

http_response_code(404);
echo 'Not Found';
