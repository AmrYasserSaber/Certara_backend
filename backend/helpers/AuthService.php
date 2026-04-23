<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\RefreshToken;
use App\Models\User;
use App\Helpers\JWTHelper;
use App\Helpers\CookieHelper;

final class AuthService
{
    public static function loginUser(User $user): array
    {
        $access = JWTHelper::generateAccessToken($user);
        $refresh = JWTHelper::generateRefreshToken($user);

        self::persistRefreshToken((int) $user->id, $refresh['jti'], $refresh['expiresAt']);

        $csrfToken = self::generateCsrfToken();

        CookieHelper::setAccessTokenCookie($access['token'], $access['expiresAt']);
        CookieHelper::setRefreshTokenCookie($refresh['token'], $refresh['expiresAt']);
        CookieHelper::setCsrfCookie($csrfToken, $refresh['expiresAt']);

        return [
            'user'    => self::buildSafeUser($user),
            'access'  => $access,
            'refresh' => $refresh,
            'csrf'    => $csrfToken,
        ];
    }

    public static function rotateRefreshToken(User $user, string $currentJti): array
    {
        self::revokeRefreshToken($currentJti, (int) $user->id);
        return self::loginUser($user);
    }

    public static function logoutUser(?string $refreshJti, ?int $userId): void
    {
        if ($userId !== null && $userId > 0) {
            RefreshToken::query()
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => date('Y-m-d H:i:s')]);
        }

        CookieHelper::clearAuthCookies();
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $passwordHash): bool
    {
        return password_verify($password, $passwordHash);
    }

    public static function buildSafeUser(User $user): array
    {
        return [
            'id'             => (int) $user->id,
            'name'           => (string) ($user->name ?? ''),
            'email'          => (string) ($user->email ?? ''),
            'phone'          => $user->phone === null ? null : (string) $user->phone,
            'national_id'    => $user->national_id === null ? null : (string) $user->national_id,
            'department'     => $user->department === null ? null : (string) $user->department,
            'faculty'        => $user->faculty === null ? null : (string) $user->faculty,
            'specialization' => $user->specialization === null ? null : (string) $user->specialization,
            'role'           => (string) ($user->role ?? ''),
            'status'         => (string) ($user->status ?? ''),
            'created_at'     => $user->created_at?->format(DATE_ATOM),
        ];
    }

    public static function isRefreshTokenActive(string $jti, int $userId): bool
    {
        return RefreshToken::query()
            ->where('jti', $jti)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->exists();
    }

    private static function persistRefreshToken(int $userId, string $jti, int $expiresAt): void
    {
        RefreshToken::create([
            'user_id'    => $userId,
            'jti'        => $jti,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'revoked_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function revokeRefreshToken(string $jti, int $userId): void
    {
        RefreshToken::query()
            ->where('jti', $jti)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    private static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}

