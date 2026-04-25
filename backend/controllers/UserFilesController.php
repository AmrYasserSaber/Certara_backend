<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Enums\IdentityPhotoType;
use App\Models\User;
use App\Models\UserAvatar;
use App\Models\UserIdentityPhoto;
use App\Services\FileUploadService;
use App\Services\ImageKitClient;
use App\Services\UploadedFileValidator;
use App\Services\UploadContext;
use App\Services\UploadException;
use App\Services\UploadStrategies\AvatarUpload;
use App\Services\UploadStrategies\StudentIdUpload;

final class UserFilesController extends Controller 
{
    public function getAvatar(Request $request): never {
        $user = $this->requireUser($request);

        /** @var UserAvatar|null $activeAvatar */
        $activeAvatar = UserAvatar::query()
            ->where('user_id', (int) $user->id)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();

        if ($activeAvatar === null) {
            $this->ok(['avatar' => null]);
        }

        $this->ok([
            'avatar' => [
                'id' => (int) $activeAvatar->id,
                'fileId' => (string) ($activeAvatar->file_id ?? ''),
                'filePath' => (string) ($activeAvatar->file_path ?? ''),
                'url' => (string) ($activeAvatar->file_url ?? ''),
            ],
        ]);
    }

    public function uploadAvatar(Request $request): never {
        $user = $this->requireUser($request);
        $service = $this->buildFileUploadService();

        try {
            $result = $service->upload($request, new AvatarUpload(), new UploadContext(actorUserId: (int) $user->id));
        } catch (UploadException $err) {
            $this->fail($err->getMessage(), $err->getStatus(), $err->getCodeString(), $err->getDetails());
        }

        /** @var UserAvatar $avatar */
        $avatar = Database::transaction(static function () use ($user, $result): UserAvatar {
            UserAvatar::query()
                ->where('user_id', (int) $user->id)
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            /** @var UserAvatar $created */
            $created = UserAvatar::create([
                'user_id' => (int) $user->id,
                'file_id' => $result->fileId,
                'file_path' => $result->filePath,
                'file_url' => $result->url,
                'original_name' => $result->originalName,
                'size_bytes' => $result->sizeBytes,
                'mime_type' => $result->mimeType,
            ]);

            $created->activate();
            return $created;
        });

        $this->created([
            'avatar' => [
                'id' => (int) $avatar->id,
                'fileId' => (string) ($avatar->file_id ?? ''),
                'filePath' => (string) ($avatar->file_path ?? ''),
                'url' => (string) ($avatar->file_url ?? ''),
            ],
        ]);
    }

    public function uploadIdentity(Request $request): never {
        $user = $this->requireUser($request);
        $data = $this->validate($request, [
            'type' => 'required|string|trim|min:4|max:5',
        ]);

        $side = (string) $data['type'];
        if (!in_array($side, IdentityPhotoType::ALL, true)) {
            $this->fail('Invalid identity photo type.', 422, 'invalid_identity_photo_type');
        }

        $service = $this->buildFileUploadService();
        $fieldName = $this->resolveIdentityFileFieldName($request, $side);
        try {
            $result = $service->upload(
                $request,
                new StudentIdUpload(side: $side, fileFieldName: $fieldName),
                new UploadContext(actorUserId: (int) $user->id),
            );
        } catch (UploadException $err) {
            $this->fail($err->getMessage(), $err->getStatus(), $err->getCodeString(), $err->getDetails());
        }

        /** @var UserIdentityPhoto $identityPhoto */
        $identityPhoto = Database::transaction(static function () use ($user, $side, $result): UserIdentityPhoto {
            UserIdentityPhoto::query()
                ->where('user_id', (int) $user->id)
                ->where('type', $side)
                ->lockForUpdate()
                ->get();

            UserIdentityPhoto::query()
                ->where('user_id', (int) $user->id)
                ->where('type', $side)
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            /** @var UserIdentityPhoto $created */
            $created = UserIdentityPhoto::create([
                'user_id' => (int) $user->id,
                'type' => $side,
                'file_id' => $result->fileId,
                'file_path' => $result->filePath,
                'file_url' => $result->url,
                'original_name' => $result->originalName,
                'size_bytes' => $result->sizeBytes,
                'mime_type' => $result->mimeType,
                'is_active' => 1,
            ]);

            return $created;
        });

        $this->created([
            'identityPhoto' => [
                'type' => $side,
                'id' => (int) $identityPhoto->id,
                'fileId' => (string) ($identityPhoto->file_id ?? ''),
                'filePath' => (string) ($identityPhoto->file_path ?? ''),
            ],
        ]);
    }

    public function getSignedIdentityUrl(Request $request): never {
        $user = $this->requireUser($request);
        $type = (string) $request->query('type', '');
        if (!in_array($type, IdentityPhotoType::ALL, true)) {
            $this->fail('Invalid identity photo type.', 422, 'invalid_identity_photo_type');
        }

        /** @var UserIdentityPhoto|null $activePhoto */
        $activePhoto = UserIdentityPhoto::query()
            ->where('user_id', (int) $user->id)
            ->where('type', $type)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();

        $filePath = $activePhoto === null ? '' : (string) ($activePhoto->file_path ?? '');

        if ($filePath === '') {
            $this->fail('Identity photo not found.', 404, 'not_found');
        }

        $expireSeconds = (int) env('IMAGEKIT_SIGNED_URL_EXPIRE_SECONDS', 300);
        $client = new ImageKitClient();
        try {
            $signedUrl = $client->buildSignedUrl($filePath, $expireSeconds);
        } catch (\InvalidArgumentException $err) {
            $this->fail('Could not generate signed URL.', 500, 'signed_url_failed', [
                'reason' => $err->getMessage(),
            ]);
        }
        $this->ok([
            'type' => $type,
            'url' => $signedUrl,
        ]);
    }

    private function requireUser(Request $request): User {
        $user = $request->user();
        if (!$user instanceof User) {
            $this->fail('Unauthenticated.', 401, 'unauthenticated');
        }
        return $user;
    }

    private function buildFileUploadService(): FileUploadService {
        return new FileUploadService(
            imageKitClient: new ImageKitClient(),
            validator: new UploadedFileValidator(),
        );
    }

    private function resolveIdentityFileFieldName(Request $request, string $side): string {
        if ($request->file('id_photo') !== null) {
            return 'id_photo';
        }

        if ($side === 'front' && $request->file('id_front') !== null) {
            return 'id_front';
        }

        if ($side === 'back' && $request->file('id_back') !== null) {
            return 'id_back';
        }

        return 'id_photo';
    }

}
