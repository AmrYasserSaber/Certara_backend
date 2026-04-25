<?php

declare(strict_types=1);

use App\Controllers\UserFilesController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;

/** @var Router $router */

$router->get('/api/users/me/avatar',            [UserFilesController::class, 'getAvatar'],     [AuthMiddleware::class]);
$router->post('/api/users/me/avatar',          [UserFilesController::class, 'uploadAvatar'],  [AuthMiddleware::class]);

$router->get('/api/users/me/identity/url',     [UserFilesController::class, 'getSignedIdentityUrl'], [AuthMiddleware::class]);

