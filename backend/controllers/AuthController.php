<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Helpers\AuthService;
use App\Helpers\JWTHelper;
use App\Models\User;

final class AuthController extends Controller
{
    public function register(Request $request): never
    {
        $data = $this->validate($request, [
            'name'       => 'required|string|trim|min:2|max:150',
            'email'      => 'required|string|trim|email|max:190',
            'password'   => 'required|string|min:8|max:255',
            'phone'      => 'nullable|string|trim|phone_eg',
            'department' => 'nullable|string|trim|min:2|max:150',
            'faculty'    => 'nullable|string|trim|min:2|max:150',
        ]);

        $email = strtolower((string) $data['email']);

        if (User::query()->where('email', $email)->exists()) {
            $this->fail('Email is already registered.', 409, 'email_taken');
        }

        $user = User::create([
            'name'          => trim((string) $data['name']),
            'email'         => $email,
            'password_hash' => AuthService::hashPassword((string) $data['password']),
            'phone'         => $data['phone'] ?? null,
            'department'    => $data['department'] ?? null,
            'faculty'       => $data['faculty'] ?? null,
            'role'          => 'student',
            'status'        => 'pending',
        ]);

        AuthService::loginUser($user);

        $this->created([
            'user' => AuthService::buildSafeUser($user),
        ]);
    }

    public function login(Request $request): never
    {
        $data = $this->validate($request, [
            'email'    => 'required|string|trim|email|max:190',
            'password' => 'required|string|min:8|max:255',
        ]);

        $email = strtolower((string) $data['email']);

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->fail('Invalid credentials.', 401, 'invalid_credentials');
        }

        if ((string) ($user->status ?? '') !== 'active') {
            $this->fail('Account is not active.', 403, 'account_inactive');
        }

        if (!AuthService::verifyPassword((string) $data['password'], (string) $user->password_hash)) {
            $this->fail('Invalid credentials.', 401, 'invalid_credentials');
        }

        AuthService::loginUser($user);

        $this->ok([
            'user' => AuthService::buildSafeUser($user),
        ]);
    }

    public function logout(Request $request): never
    {
        $refreshJwt = $_COOKIE[\App\Helpers\CookieHelper::COOKIE_REFRESH] ?? null;

        $refreshJti = null;
        $userId = null;

        if (is_string($refreshJwt) && $refreshJwt !== '') {
            try {
                $payload = JWTHelper::verifyToken($refreshJwt, JWTHelper::TOKEN_TYPE_REFRESH);
                $refreshJti = (string) $payload['jti'];
                $userId = (int) $payload['sub'];
            } catch (\Throwable) {
                $refreshJti = null;
                $userId = null;
            }
        }

        AuthService::logoutUser($refreshJti, $userId);

        $this->ok(['ok' => true]);
    }

    public function me(Request $request): never
    {
        $user = $request->user();
        if (!$user instanceof User) {
            $this->fail('Unauthenticated.', 401, 'unauthenticated');
        }

        $this->ok(['user' => AuthService::buildSafeUser($user)]);
    }

    public function test(Request $request): never
    {
        $user = $request->user();
        if (!$user instanceof User) {
            $this->fail('Unauthenticated.', 401, 'unauthenticated');
        }

        $this->ok([
            'ok' => true,
            'user_id' => (int) $user->id,
        ]);
    }
}
