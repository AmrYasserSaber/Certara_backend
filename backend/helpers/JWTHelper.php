<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JWTHelper
{
    public const TOKEN_TYPE_ACCESS = 'access';
    public const TOKEN_TYPE_REFRESH = 'refresh';

    public static function generateAccessToken(User $user): array
    {
        return self::generateToken($user, self::TOKEN_TYPE_ACCESS, self::accessTtlSeconds());
    }

    public static function generateRefreshToken(User $user): array
    {
        return self::generateToken($user, self::TOKEN_TYPE_REFRESH, self::refreshTtlSeconds());
    }

    public static function verifyToken(string $jwt, string $expectedType): array
    {
        $secret = (string) env('JWT_SECRET');
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }

        $algo = (string) env('JWT_ALGO', 'HS256');
        $decoded = JWT::decode($jwt, new Key($secret, $algo));

        $payload = (array) $decoded;
        $payloadType = (string) ($payload['typ'] ?? '');
        if ($payloadType !== $expectedType) {
            throw new \RuntimeException('Invalid token type.');
        }

        $issuer = (string) env('JWT_ISSUER', 'irb-system');
        if (($payload['iss'] ?? null) !== $issuer) {
            throw new \RuntimeException('Invalid token issuer.');
        }

        $subject = (int) ($payload['sub'] ?? 0);
        if ($subject <= 0) {
            throw new \RuntimeException('Invalid token subject.');
        }

        $jti = (string) ($payload['jti'] ?? '');
        if ($jti === '') {
            throw new \RuntimeException('Invalid token id.');
        }

        return [
            'sub'  => $subject,
            'role' => (string) ($payload['role'] ?? ''),
            'typ'  => $payloadType,
            'jti'  => $jti,
            'exp'  => (int) ($payload['exp'] ?? 0),
            'iat'  => (int) ($payload['iat'] ?? 0),
            'iss'  => (string) ($payload['iss'] ?? ''),
        ];
    }

    private static function generateToken(User $user, string $type, int $ttlSeconds): array
    {
        $now = time();
        $expiresAt = $now + $ttlSeconds;

        $secret = (string) env('JWT_SECRET');
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }

        $issuer = (string) env('JWT_ISSUER', 'irb-system');
        $algo = (string) env('JWT_ALGO', 'HS256');

        $jti = self::generateTokenId();

        $payload = [
            'iss'  => $issuer,
            'sub'  => (int) $user->id,
            'role' => (string) ($user->role ?? ''),
            'typ'  => $type,
            'jti'  => $jti,
            'iat'  => $now,
            'exp'  => $expiresAt,
        ];

        return [
            'token'     => JWT::encode($payload, $secret, $algo),
            'expiresAt' => $expiresAt,
            'jti'       => $jti,
        ];
    }

    private static function accessTtlSeconds(): int
    {
        return max(60, (int) env('JWT_ACCESS_TTL', 900));
    }

    private static function refreshTtlSeconds(): int
    {
        return max(600, (int) env('JWT_REFRESH_TTL', 1209600));
    }

    private static function generateTokenId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
