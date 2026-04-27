<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\CookieHelper;

final class CsrfMiddleware implements Middleware
{
    /** @var list<string> */
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const CSRF_SKIP_PATHS = [
        '/api/health',
        '/api/auth/login',
        '/api/auth/register',
        '/api/payment/callback',
    ];

    public function handle(Request $request): Request
    {
        if ($request->method() === 'OPTIONS') {
            return $request;
        }

        if (!in_array($request->method(), self::STATE_CHANGING_METHODS, true)) {
            return $request;
        }

        if (in_array($request->path(), self::CSRF_SKIP_PATHS, true)) {
            return $request;
        }

        $cookieToken = $_COOKIE[CookieHelper::COOKIE_CSRF] ?? null;
        $headerToken = $request->header('x-csrf-token');

        if (!is_string($cookieToken) || $cookieToken === '' || !is_string($headerToken) || $headerToken === '') {
            Response::error('CSRF token missing.', 403, 'csrf_missing');
        }

        if (!hash_equals($cookieToken, $headerToken)) {
            Response::error('CSRF token mismatch.', 403, 'csrf_mismatch');
        }

        return $request;
    }
}

