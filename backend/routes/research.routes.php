<?php

declare(strict_types=1);

use App\Controllers\ResearchController;
use App\Controllers\DocumentController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

/** @var Router $router */

$router->post('/api/research',      [ResearchController::class, 'store'],  [AuthMiddleware::class]);
$router->get('/api/research',       [ResearchController::class, 'index'],  [AuthMiddleware::class]);
$router->get('/api/research/{id}',  [ResearchController::class, 'show'],   [AuthMiddleware::class]);
$router->put('/api/research/{id}',  [ResearchController::class, 'update'], [AuthMiddleware::class]);

// Document routes
$router->post('/api/research/{id}/documents',           [DocumentController::class, 'store'],   [AuthMiddleware::class]);
$router->get('/api/research/{id}/documents',            [DocumentController::class, 'index'],   [AuthMiddleware::class]);
$router->delete('/api/research/{id}/documents/{doc_id}', [DocumentController::class, 'destroy'], [AuthMiddleware::class]);
