<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\AuthService;
use App\Helpers\CookieHelper;
use App\Helpers\JWTHelper;
use App\Models\User;

final class AuthMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        $accessToken = $_COOKIE[CookieHelper::COOKIE_ACCESS] ?? null;
        if (is_string($accessToken) && $accessToken !== '') {
            $user = $this->authenticateWithAccessToken($accessToken);
            if ($user !== null) {
                $request->setAttribute('user', $user);
                return $request;
            }
        }

        $refreshToken = $_COOKIE[CookieHelper::COOKIE_REFRESH] ?? null;
        if (!is_string($refreshToken) || $refreshToken === '') {
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        try {
            $payload = JWTHelper::verifyToken($refreshToken, JWTHelper::TOKEN_TYPE_REFRESH);
        } catch (\Throwable) {
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        $userId = (int) $payload['sub'];
        $jti = (string) $payload['jti'];

        if (!AuthService::isRefreshTokenActive($jti, $userId)) {
            CookieHelper::clearAuthCookies();
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if ($user === null) {
            CookieHelper::clearAuthCookies();
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        AuthService::rotateRefreshToken($user, $jti);

        $request->setAttribute('user', $user);
        return $request;
    }

    private function authenticateWithAccessToken(string $accessToken): ?User
    {
        try {
            $payload = JWTHelper::verifyToken($accessToken, JWTHelper::TOKEN_TYPE_ACCESS);
        } catch (\Throwable) {
            return null;
        }

        $userId = (int) $payload['sub'];
        if ($userId <= 0) {
            return null;
        }

        /** @var User|null $user */
        $user = User::find($userId);
        return $user;
    }
}
