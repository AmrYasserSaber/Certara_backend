<?php

declare(strict_types=1);


use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\CsrfMiddleware;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/env.php';

$debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    Logger::error('Unhandled exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    $payload = $debug
        ? ['trace' => explode("\n", $e->getTraceAsString())]
        : null;
    Response::error(
        $debug ? $e->getMessage() : 'Internal server error.',
        500,
        'server_error',
        $payload,
    );
});

register_shutdown_function(static function (): void {
    $lastError = error_get_last();
    if ($lastError === null) {
        return;
    }

    if (in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::error('Fatal shutdown error', [
            'type' => $lastError['type'],
            'message' => $lastError['message'] ?? '',
            'file' => $lastError['file'] ?? '',
            'line' => $lastError['line'] ?? 0,
        ]);
    }
});

require __DIR__ . '/config/cors.php';

$dbConfig = require __DIR__ . '/config/database.php';
$default  = $dbConfig['default'] ?? 'mysql';
Database::boot($dbConfig['connections'][$default]);

Logger::info('Incoming request', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

$router = new Router();
$router->useGlobal([CsrfMiddleware::class]);

$routeFiles = [
    __DIR__ . '/routes/health.routes.php',
    __DIR__ . '/routes/auth.routes.php',
    __DIR__ . '/routes/notification.routes.php',
    // __DIR__ . '/routes/research.routes.php',   // DEV 2
    // __DIR__ . '/routes/review.routes.php',     // DEV 3
    // __DIR__ . '/routes/admin.routes.php',      // DEV 4
];

foreach ($routeFiles as $file) {
    if (is_file($file)) {
        (static function (string $file, Router $router): void {
            require $file;
        })($file, $router);
    }
}

$router->dispatch(Request::capture());
