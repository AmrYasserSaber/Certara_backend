<?php

declare(strict_types=1);

use App\Controllers\NotificationController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

/** @var Router $router */

$router->get('/api/notifications',              [NotificationController::class, 'index'],        [AuthMiddleware::class]);
$router->put('/api/notifications/read-all',     [NotificationController::class, 'markAllRead'],  [AuthMiddleware::class]);
$router->put('/api/notifications/{id}/read',    [NotificationController::class, 'markRead'],     [AuthMiddleware::class]);
~`