<?php

declare(strict_types=1);

use App\Controllers\ResearchController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

/** @var Router $router */

$router->post('/api/research',      [ResearchController::class, 'store'],  [AuthMiddleware::class]);
$router->get('/api/research',       [ResearchController::class, 'index'],  [AuthMiddleware::class]);
$router->get('/api/research/{id}',  [ResearchController::class, 'show'],   [AuthMiddleware::class]);
$router->put('/api/research/{id}',  [ResearchController::class, 'update'], [AuthMiddleware::class]);
