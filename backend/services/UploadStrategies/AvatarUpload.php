<?php

declare(strict_types=1);

namespace App\Services\UploadStrategies;

use App\Services\UploadableFile;
use App\Services\UploadContext;
use App\Services\UploadedPhpFile;

final class AvatarUpload implements UploadableFile
{
    private const MAX_BYTES = 5242880;

    public function getCategory(): string {
        return 'avatar';
    }

    public function getFileFieldName(): string {
        return 'avatar';
    }

    public function getAllowedMimeTypes(): array {
        return ['image/jpeg', 'image/png'];
    }

    public function getMaxBytes(): int {
        return self::MAX_BYTES;
    }

    public function getFolderPath(UploadContext $context): string {
        return '/users/' . $context->actorUserId . '/avatar';
    }

    public function getSafeFileName(UploadContext $context, UploadedPhpFile $file): string {
        return 'avatar_' . $context->actorUserId . '_' . time() . '.bin';
    }

    public function isSensitive(): bool {
        return false;
    }

    public function getTransformations(): array {
        return [];
    }

    public function getChecksExpression(): ?string {
        return null;
    }
}

