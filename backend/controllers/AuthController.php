<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Logger;
use App\Enums\IdentityPhotoType;
use App\Helpers\AuthService;
use App\Helpers\EmailHelper;
use App\Helpers\JWTHelper;
use App\Models\User;
use App\Models\UserIdentityPhoto;
use App\Services\FileUploadService;
use App\Services\ImageKitClient;
use App\Services\UploadedFileValidator;
use App\Services\UploadContext;
use App\Services\UploadException;
use App\Services\UploadStrategies\StudentIdFrontUpload;
use App\Services\UploadStrategies\StudentIdBackUpload;

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
            'password_confirmation' => 'required|string|min:8|max:255',
            'phone'      => 'required|string|trim|phone_eg',
            'national_id' => 'required|string|trim|regex:/^\\d{14}$/',
            'department' => 'required|string|trim|min:2|max:150',
            'faculty'    => 'required|string|trim|min:2|max:150',
            'specialization' => 'required|string|trim|min:2|max:150',
        ]);

        if ((string) $data['password'] !== (string) $data['password_confirmation']) {
            $this->fail('فشل التحقق.', 422, 'validation_error', [
                'password_confirmation' => 'يجب أن يتطابق تأكيد كلمة المرور مع كلمة المرور',
            ]);
        }

        $email = strtolower((string) $data['email']);

        if (User::query()->where('email', $email)->exists()) {
            Logger::warning('Register blocked: email exists', ['email' => $email]);
            $this->fail('البريد الإلكتروني مسجل بالفعل.', 409, 'email_taken');
        }

        if (User::query()->where('national_id', (string) $data['national_id'])->exists()) {
            $this->fail('رقم الهوية مسجل بالفعل.', 409, 'national_id_taken');
        }

        if ($request->file('id_front') === null || $request->file('id_back') === null) {
            $this->fail('فشل التحقق.', 422, 'validation_error', [
                'id_front' => $request->file('id_front') === null ? 'صورة الهوية الأمامية مطلوبة' : null,
                'id_back' => $request->file('id_back') === null ? 'صورة الهوية الخلفية مطلوبة' : null,
            ]);
        }

        /** @var User $user */
        $user = null;
        try {
            $user = User::create([
                'name'          => trim((string) $data['name']),
                'email'         => $email,
                'password_hash' => AuthService::hashPassword((string) $data['password']),
                'phone'         => $data['phone'],
                'national_id'   => $data['national_id'],
                'department'    => $data['department'],
                'faculty'       => $data['faculty'],
                'specialization' => $data['specialization'],
                'role'          => 'student',
                'status'        => 'pending',
            ]);

            $request->setAttribute('user', $user);
            $uploadService = $this->buildFileUploadService();
            $context = new UploadContext(actorUserId: (int) $user->id);

            $front = $uploadService->upload($request, new StudentIdFrontUpload(), $context);
            $back  = $uploadService->upload($request, new StudentIdBackUpload(), $context);

            Database::transaction(static function () use ($user, $front, $back): void {
                UserIdentityPhoto::query()
                    ->where('user_id', (int) $user->id)
                    ->where('type', IdentityPhotoType::FRONT)
                    ->where('is_active', 1)
                    ->update(['is_active' => 0]);

                UserIdentityPhoto::query()
                    ->where('user_id', (int) $user->id)
                    ->where('type', IdentityPhotoType::BACK)
                    ->where('is_active', 1)
                    ->update(['is_active' => 0]);

                UserIdentityPhoto::create([
                    'user_id'        => (int) $user->id,
                    'type'           => IdentityPhotoType::FRONT,
                    'file_id'        => $front->fileId,
                    'file_path'      => $front->filePath,
                    'file_url'       => $front->url,
                    'original_name'  => $front->originalName,
                    'size_bytes'     => $front->sizeBytes,
                    'mime_type'      => $front->mimeType,
                    'is_active'      => 1,
                ]);

                UserIdentityPhoto::create([
                    'user_id'        => (int) $user->id,
                    'type'           => IdentityPhotoType::BACK,
                    'file_id'        => $back->fileId,
                    'file_path'      => $back->filePath,
                    'file_url'       => $back->url,
                    'original_name'  => $back->originalName,
                    'size_bytes'     => $back->sizeBytes,
                    'mime_type'      => $back->mimeType,
                    'is_active'      => 1,
                ]);
            });
        } catch (UploadException $err) {
            if ($user instanceof User) {
                $user->delete();
            }
            $this->fail($err->getMessage(), $err->getStatus(), $err->getCodeString(), $err->getDetails());
        } catch (\Throwable $err) {
            if ($user instanceof User) {
                $user->delete();
            }
            throw $err;
        }

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

    private function buildFileUploadService(): FileUploadService
    {
        return new FileUploadService(
            imageKitClient: new ImageKitClient(),
            validator: new UploadedFileValidator(),
        );
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

        $user = User::query()
            ->where('email', $email)
            ->with('activeAvatar')
            ->first();
        if ($user === null) {
            Logger::warning('Login failed: user not found', ['email' => $email]);
            $this->fail('بيانات الاعتماد غير صالحة.', 401, 'invalid_credentials');
        }

        if ((string) ($user->status ?? '') !== 'active') {
            Logger::warning('Login blocked: inactive account', [
                'user_id' => (int) $user->id,
                'email' => (string) $user->email,
                'status' => (string) $user->status,
            ]);
            $this->fail('الحساب غير نشط.', 403, 'account_inactive');
        }

        if (!AuthService::verifyPassword((string) $data['password'], (string) $user->password_hash)) {
            Logger::warning('Login failed: password mismatch', [
                'user_id' => (int) $user->id,
                'email' => (string) $user->email,
            ]);
            $this->fail('بيانات الاعتماد غير صالحة.', 401, 'invalid_credentials');
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
            $this->fail('غير مصرح بالدخول.', 401, 'unauthenticated');
        }

        $this->ok(['user' => AuthService::buildSafeUser($user)]);
    }

    public function test(Request $request): never
    {
        $user = $request->user();
        if (!$user instanceof User) {
            $this->fail('غير مصرح بالدخول.', 401, 'unauthenticated');
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

        $subject = 'تم إنشاء حسابك (في انتظار التفعيل)';
        $name = htmlspecialchars((string) ($user->name ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $body = <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
  <body style="font-family: Tahoma, Arial, sans-serif; background:#f6f6f6; padding:20px; text-align: right;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:24px;">
      <h2 style="color:#0b3d91;margin:0 0 16px;">مرحباً{$this->renderNameSuffix($name)}</h2>
      <p style="line-height:1.7;color:#333;margin:0 0 12px;">
        لقد تم إنشاء حساب الطالب الخاص بك بنجاح.
      </p>
      <p style="line-height:1.7;color:#333;margin:0 0 12px;">
        لا يزال حسابك بحاجة إلى التفعيل من قبل الإدارة قبل أن تتمكن من تسجيل الدخول.
      </p>
      <p style="line-height:1.7;color:#333;margin:0 0 12px;">
        ستتلقى رسالة بريد إلكتروني أخرى بمجرد تفعيل حسابك.
      </p>
      <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
      <p style="color:#888;font-size:12px;margin:0;">نظام IRB الرقمي - بريد إلكتروني تلقائي.</p>
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
