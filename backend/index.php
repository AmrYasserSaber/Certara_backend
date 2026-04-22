<?php

declare(strict_types=1);

/**
 * IRB Digital System — REST API front controller.
 * DEV 5-owned. To add routes, create routes/<domain>.routes.php and add it
 * to $routeFiles below via PR.
 */

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/env.php';

$debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

set_exception_handler(static function (\Throwable $e) use ($debug): void {
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

require __DIR__ . '/config/cors.php';

$dbConfig = require __DIR__ . '/config/database.php';
$default  = $dbConfig['default'] ?? 'mysql';
Database::boot($dbConfig['connections'][$default]);

$router = new Router();

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
