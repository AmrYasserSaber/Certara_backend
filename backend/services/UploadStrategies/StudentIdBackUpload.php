<?php

declare(strict_types=1);

namespace App\Services\UploadStrategies;

use App\Services\UploadableFile;
use App\Services\UploadContext;
use App\Services\UploadedPhpFile;

final class StudentIdBackUpload implements UploadableFile
{
    private const MAX_BYTES = 5242880;

    public function getCategory(): string {
        return 'student_id_back';
    }

    public function getFileFieldName(): string {
        return 'id_back';
    }

    public function getAllowedMimeTypes(): array {
        return ['image/jpeg', 'image/png'];
    }

    public function getMaxBytes(): int {
        return self::MAX_BYTES;
    }

    public function getFolderPath(UploadContext $context): string {
        return '/users/' . $context->actorUserId . '/identity';
    }

    public function getSafeFileName(UploadContext $context, UploadedPhpFile $file): string {
        return 'id_back_' . $context->actorUserId . '_' . time() . '.bin';
    }

    public function isSensitive(): bool {
        return true;
    }

    public function getTransformations(): array {
        return [];
    }

    public function getChecksExpression(): ?string {
        return null;
    }
}

