<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

/** @var Router $router */

$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login',    [AuthController::class, 'login']);

$router->post('/api/auth/logout',   [AuthController::class, 'logout'], [AuthMiddleware::class]);
$router->get('/api/auth/me',        [AuthController::class, 'me'],     [AuthMiddleware::class]);

$router->get('/api/auth/test',      [AuthController::class, 'test'],   [AuthMiddleware::class]);
