<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;

final class HealthController extends Controller
{
    public function index(Request $request): never
    {
        $dbOk    = true;
        $dbError = null;

        try {
            Database::fetchOne('SELECT 1 AS ok');
        } catch (\Throwable $e) {
            $dbOk    = false;
            $dbError = $e->getMessage();
        }

        $this->ok([
            'service'   => (string) env('APP_NAME', 'IRB System'),
            'env'       => (string) env('APP_ENV', 'local'),
            'time'      => date('c'),
            'database'  => ['ok' => $dbOk, 'error' => $dbError],
            'php'       => PHP_VERSION,
        ]);
    }
}
