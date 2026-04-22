<?php

declare(strict_types=1);

namespace App\Helpers;

final class CookieHelper
{
    public const COOKIE_ACCESS = 'IRB_ACCESS_TOKEN';
    public const COOKIE_REFRESH = 'IRB_REFRESH_TOKEN';
    public const COOKIE_CSRF = 'IRB_CSRF_TOKEN';

    public static function setAccessTokenCookie(string $token, int $expiresAt): void
    {
        self::setCookie(self::COOKIE_ACCESS, $token, $expiresAt, '/', true);
    }

    public static function setRefreshTokenCookie(string $token, int $expiresAt): void
    {
        self::setCookie(self::COOKIE_REFRESH, $token, $expiresAt, '/api/auth', true);
    }

    public static function setCsrfCookie(string $token, int $expiresAt): void
    {
        self::setCookie(self::COOKIE_CSRF, $token, $expiresAt, '/', false);
    }

    public static function clearAuthCookies(): void
    {
        self::clearCookie(self::COOKIE_ACCESS, '/');
        self::clearCookie(self::COOKIE_REFRESH, '/api/auth');
        self::clearCookie(self::COOKIE_CSRF, '/');
    }

    private static function setCookie(
        string $name,
        string $value,
        int $expiresAt,
        string $path,
        bool $isHttpOnly
    ): void
    {
        $isSecure = self::isSecureCookie();
        $sameSite = self::sameSiteMode();

        setcookie($name, $value, [
            'expires'  => $expiresAt,
            'path'     => $path,
            'secure'   => $isSecure,
            'httponly' => $isHttpOnly,
            'samesite' => $sameSite,
        ]);
    }

    private static function clearCookie(string $name, string $path): void
    {
        $isSecure = self::isSecureCookie();
        $sameSite = self::sameSiteMode();

        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => $path,
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }

    private static function isSecureCookie(): bool
    {
        $envSecure = env('COOKIE_SECURE', null);
        if (is_bool($envSecure)) {
            return $envSecure;
        }

        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }

        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        return strtolower((string) $forwardedProto) === 'https';
    }

    private static function sameSiteMode(): string
    {
        $mode = (string) env('COOKIE_SAMESITE', 'Lax');
        $mode = ucfirst(strtolower($mode));

        if (!in_array($mode, ['Lax', 'Strict', 'None'], true)) {
            return 'Lax';
        }

        if ($mode === 'None' && !self::isSecureCookie()) {
            return 'Lax';
        }

        return $mode;
    }
}

