<?php

declare(strict_types=1);

use App\Controllers\ResearchController;
use App\Controllers\PaymentController;
use App\Controllers\DocumentController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

/** @var Router $router */

$router->post('/api/research',      [ResearchController::class, 'store'],  [AuthMiddleware::class]);
$router->get('/api/research',       [ResearchController::class, 'index'],  [AuthMiddleware::class]);
$router->get('/api/research/{id}',  [ResearchController::class, 'show'],   [AuthMiddleware::class]);
$router->put('/api/research/{id}',  [ResearchController::class, 'update'], [AuthMiddleware::class]);
$router->patch('/api/research/{id}',  [ResearchController::class, 'update'], [AuthMiddleware::class]);

// Payment routes
$router->post('/api/research/{id}/payment',         [PaymentController::class, 'store'],  [AuthMiddleware::class]);
$router->get('/api/research/{id}/payment/receipt',  [PaymentController::class, 'receipt'], [AuthMiddleware::class]);
$router->get('/api/payment/callback',               [PaymentController::class, 'responseCallback']); // Public redirect callback (UX)
$router->post('/api/payment/callback',              [PaymentController::class, 'callback']); // Public callback

// Document routes
$router->post('/api/research/{id}/documents',           [DocumentController::class, 'store'],   [AuthMiddleware::class]);
$router->get('/api/research/{id}/documents',            [DocumentController::class, 'index'],   [AuthMiddleware::class]);
$router->delete('/api/research/{id}/documents/{doc_id}', [DocumentController::class, 'destroy'], [AuthMiddleware::class]);
