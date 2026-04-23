<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Logger;
use App\Helpers\AuthService;
use App\Helpers\EmailHelper;
use App\Helpers\JWTHelper;
use App\Models\User;

final class AuthController extends Controller
{
    public function register(Request $request): never
    {
        Logger::info('Auth register attempt', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        $data = $this->validate($request, [
            'name'       => 'required|string|trim|min:2|max:150',
            'email'      => 'required|string|trim|email|max:190',
            'password'   => 'required|string|min:8|max:255',
            'phone'      => 'nullable|string|trim|phone_eg',
            'department' => 'nullable|string|trim|min:2|max:150',
            'faculty'    => 'nullable|string|trim|min:2|max:150',
            'specialization' => 'nullable|string|trim|min:2|max:150',
        ]);

        Logger::info('Data', ['data' => $data]);

        $email = strtolower((string) $data['email']);

        if (User::query()->where('email', $email)->exists()) {
            Logger::warning('Register blocked: email exists', ['email' => $email]);
            $this->fail('Email is already registered.', 409, 'email_taken');
        }

        $user = User::create([
            'name'          => trim((string) $data['name']),
            'email'         => $email,
            'password_hash' => AuthService::hashPassword((string) $data['password']),
            'phone'         => $data['phone'] ?? null,
            'department'    => $data['department'] ?? null,
            'faculty'       => $data['faculty'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'role'          => 'student',
            'status'        => 'pending',
        ]);

        $this->sendRegistrationPendingActivationEmail($user);

        Logger::info('Auth register success', [
            'user_id' => (int) $user->id,
            'email' => (string) $user->email,
            'status' => (string) $user->status,
        ]);

        $this->created([
            'user' => AuthService::buildSafeUser($user),
        ]);
    }

    public function login(Request $request): never
    {
        Logger::info('Auth login attempt', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        $data = $this->validate($request, [
            'email'    => 'required|string|trim|email|max:190',
            'password' => 'required|string|min:8|max:255',
        ]);

        $email = strtolower((string) $data['email']);

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            Logger::warning('Login failed: user not found', ['email' => $email]);
            $this->fail('Invalid credentials.', 401, 'invalid_credentials');
        }

        if ((string) ($user->status ?? '') !== 'active') {
            Logger::warning('Login blocked: inactive account', [
                'user_id' => (int) $user->id,
                'email' => (string) $user->email,
                'status' => (string) $user->status,
            ]);
            $this->fail('Account is not active.', 403, 'account_inactive');
        }

        if (!AuthService::verifyPassword((string) $data['password'], (string) $user->password_hash)) {
            Logger::warning('Login failed: password mismatch', [
                'user_id' => (int) $user->id,
                'email' => (string) $user->email,
            ]);
            $this->fail('Invalid credentials.', 401, 'invalid_credentials');
        }

        AuthService::loginUser($user);

        Logger::info('Auth login success', [
            'user_id' => (int) $user->id,
            'email' => (string) $user->email,
            'role' => (string) $user->role,
        ]);

        $this->ok([
            'user' => AuthService::buildSafeUser($user),
        ]);
    }

    public function logout(Request $request): never
    {
        Logger::info('Auth logout attempt', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

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

        Logger::info('Auth logout success', [
            'user_id' => $userId,
            'had_refresh_jti' => $refreshJti !== null,
        ]);

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

    private function sendRegistrationPendingActivationEmail(User $user): void
    {
        $to = (string) ($user->email ?? '');
        if ($to === '') {
            return;
        }

        $subject = 'Your account was created (activation pending)';
        $name = htmlspecialchars((string) ($user->name ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $body = <<<HTML
<!doctype html>
<html lang="en">
  <body style="font-family: Tahoma, Arial, sans-serif; background:#f6f6f6; padding:20px;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:24px;">
      <h2 style="color:#0b3d91;margin:0 0 16px;">Welcome{$this->renderNameSuffix($name)}</h2>
      <p style="line-height:1.7;color:#333;margin:0 0 12px;">
        Your student account has been created successfully.
      </p>
      <p style="line-height:1.7;color:#333;margin:0 0 12px;">
        Your account still needs to be activated by the administration before you can sign in.
      </p>
      <p style="line-height:1.7;color:#333;margin:0 0 12px;">
        You will receive another email once your account is activated.
      </p>
      <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
      <p style="color:#888;font-size:12px;margin:0;">IRB Digital System — automated email.</p>
    </div>
  </body>
</html>
HTML;

        EmailHelper::send($to, $subject, $body, true);
    }

    private function renderNameSuffix(string $escapedName): string
    {
        if ($escapedName === '') {
            return '';
        }
        return ', ' . $escapedName;
    }
}
