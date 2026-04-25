<?php

declare(strict_types=1);

namespace App\Services\UploadStrategies;

use App\Services\UploadableFile;
use App\Services\UploadContext;
use App\Services\UploadedPhpFile;

final class CertificateUpload implements UploadableFile
{
    private const MAX_BYTES = 10485760;

    public function getCategory(): string {
        return 'certificate';
    }

    public function getFileFieldName(): string {
        return 'certificate';
    }

    public function getAllowedMimeTypes(): array {
        return ['application/pdf', 'image/jpeg', 'image/png'];
    }

    public function getMaxBytes(): int {
        return self::MAX_BYTES;
    }

    public function getFolderPath(UploadContext $context): string {
        return '/users/' . $context->actorUserId . '/certificates';
    }

    public function getSafeFileName(UploadContext $context, UploadedPhpFile $file): string {
        return 'certificate_' . $context->actorUserId . '_' . time() . '.bin';
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

