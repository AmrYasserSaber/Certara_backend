<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Logger;
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
                Logger::debug('Auth middleware authenticated via access token', [
                    'user_id' => (int) $user->id,
                ]);
                $request->setAttribute('user', $user);
                return $request;
            }
        }

        $refreshToken = $_COOKIE[CookieHelper::COOKIE_REFRESH] ?? null;
        if (!is_string($refreshToken) || $refreshToken === '') {
            Logger::warning('Auth middleware failed: missing refresh token');
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        try {
            $payload = JWTHelper::verifyToken($refreshToken, JWTHelper::TOKEN_TYPE_REFRESH);
        } catch (\Throwable $e) {
            Logger::warning('Auth middleware failed: invalid refresh token', [
                'error' => $e->getMessage(),
            ]);
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        $userId = (int) $payload['sub'];
        $jti = (string) $payload['jti'];

        if (!AuthService::isRefreshTokenActive($jti, $userId)) {
            Logger::warning('Auth middleware failed: refresh token inactive', [
                'user_id' => $userId,
                'jti' => $jti,
            ]);
            CookieHelper::clearAuthCookies();
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if ($user === null) {
            Logger::warning('Auth middleware failed: user not found', ['user_id' => $userId]);
            CookieHelper::clearAuthCookies();
            Response::error('Unauthenticated.', 401, 'unauthenticated');
        }

        if ((string) ($user->status ?? '') !== 'active') {
            Logger::warning('Auth middleware blocked: inactive account', [
                'user_id' => (int) $user->id,
                'status' => (string) ($user->status ?? ''),
            ]);
            CookieHelper::clearAuthCookies();
            Response::error('Account is not active.', 403, 'account_inactive');
        }

        AuthService::rotateRefreshToken($user, $jti);
        Logger::debug('Auth middleware authenticated via refresh token', [
            'user_id' => (int) $user->id,
            'jti' => $jti,
        ]);

        $request->setAttribute('user', $user);
        return $request;
    }

    private function authenticateWithAccessToken(string $accessToken): ?User
    {
        try {
            $payload = JWTHelper::verifyToken($accessToken, JWTHelper::TOKEN_TYPE_ACCESS);
        } catch (\Throwable $e) {
            Logger::debug('Access token verify failed', ['error' => $e->getMessage()]);
            return null;
        }

        $userId = (int) $payload['sub'];
        if ($userId <= 0) {
            return null;
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if ($user !== null && (string) ($user->status ?? '') !== 'active') {
            Logger::warning('Access token rejected: inactive account', [
                'user_id' => (int) $user->id,
                'status' => (string) ($user->status ?? ''),
            ]);
            CookieHelper::clearAuthCookies();
            Response::error('Account is not active.', 403, 'account_inactive');
        }
        return $user;
    }
}
