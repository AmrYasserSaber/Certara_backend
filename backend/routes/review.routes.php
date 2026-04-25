<?php

declare(strict_types=1);

use App\Controllers\ReviewController;
use App\Controllers\SampleSizeController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/** @var Router $router */

$router->get('/api/sample-size/pending', [SampleSizeController::class, 'pending'], [
    AuthMiddleware::class,
    new RoleMiddleware(['sample_size_officer']),
]);

$router->post('/api/sample-size/{research_id}', [SampleSizeController::class, 'submit'], [
    AuthMiddleware::class,
    new RoleMiddleware(['sample_size_officer']),
]);

$router->get('/api/reviews/assigned', [ReviewController::class, 'assigned'], [
    AuthMiddleware::class,
    new RoleMiddleware(['reviewer']),
]);

$router->get('/api/reviews/{research_id}', [ReviewController::class, 'show'], [
    AuthMiddleware::class,
    new RoleMiddleware(['reviewer']),
]);

$router->post('/api/reviews/{research_id}/comment', [ReviewController::class, 'addComment'], [
    AuthMiddleware::class,
    new RoleMiddleware(['reviewer']),
]);

$router->put('/api/reviews/{research_id}/decision', [ReviewController::class, 'submitDecision'], [
    AuthMiddleware::class,
    new RoleMiddleware(['reviewer']),
]);
