<?php

declare(strict_types=1);

use App\Controllers\CertificateController;
use App\Controllers\ManagerController;
use App\Core\Router;
use App\Enums\Roles;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/** @var Router $router */

$middleware = [
    AuthMiddleware::class,
    new RoleMiddleware([Roles::MANAGER]),
];

$router->get('/api/manager/research/reviewed', [ManagerController::class, 'reviewedQueue'], $middleware);
$router->get('/api/manager/research/{id}', [ManagerController::class, 'researchDetail'], $middleware);
$router->put('/api/manager/research/{id}/decision', [ManagerController::class, 'decision'], $middleware);
$router->get('/api/manager/dashboard/stats', [ManagerController::class, 'stats'], $middleware);
$router->post('/api/manager/research/{id}/certificate', [CertificateController::class, 'generate'], $middleware);
$router->get('/api/research/{id}/certificate', [CertificateController::class, 'download'], [AuthMiddleware::class]);
