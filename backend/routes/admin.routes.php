<?php

declare(strict_types=1);

use App\Controllers\CertificateController;
use App\Controllers\AdminController;
use App\Controllers\ManagerController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/** @var Router $router */

$adminMiddleware = [
    AuthMiddleware::class,
    new RoleMiddleware(['admin']),
];

$managerMiddleware = [
    AuthMiddleware::class,
    new RoleMiddleware(['manager']),
];

$certificateViewMiddleware = [
    AuthMiddleware::class,
    new RoleMiddleware(['admin', 'manager', 'student']),
];

$router->get('/api/admin/users/pending', [AdminController::class, 'pendingUsers'], $adminMiddleware);
$router->put('/api/admin/users/{id}/activate', [AdminController::class, 'activateUser'], $adminMiddleware);
$router->put('/api/admin/users/{id}/reject', [AdminController::class, 'rejectUser'], $adminMiddleware);
$router->get('/api/admin/research', [AdminController::class, 'researchList'], $adminMiddleware);
$router->get('/api/admin/research/{id}', [AdminController::class, 'researchDetail'], $adminMiddleware);
$router->put('/api/admin/research/{id}/assign-reviewer', [AdminController::class, 'assignReviewer'], $adminMiddleware);
$router->post('/api/admin/research/{id}/serial', [AdminController::class, 'generateSerial'], $adminMiddleware);
$router->post('/api/admin/research/{id}/second-payment', [AdminController::class, 'setSecondPayment'], $adminMiddleware);
$router->get('/api/admin/logs', [AdminController::class, 'logs'], $adminMiddleware);
$router->get('/api/admin/reviewers', [AdminController::class, 'reviewers'], $adminMiddleware);

$router->get('/api/manager/research/reviewed', [ManagerController::class, 'reviewedQueue'], $managerMiddleware);
$router->get('/api/manager/research/{id}', [ManagerController::class, 'researchDetail'], $managerMiddleware);
$router->put('/api/manager/research/{id}/decision', [ManagerController::class, 'decision'], $managerMiddleware);
$router->get('/api/manager/dashboard/stats', [ManagerController::class, 'stats'], $managerMiddleware);
$router->post('/api/manager/research/{id}/certificate', [CertificateController::class, 'generate'], $managerMiddleware);
$router->get('/api/research/{id}/certificate', [CertificateController::class, 'download'], $certificateViewMiddleware);
