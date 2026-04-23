<?php

declare(strict_types=1);

namespace App\Services\UploadStrategies;

use App\Services\UploadableFile;
use App\Services\UploadContext;
use App\Services\UploadedPhpFile;

final class DocumentUpload implements UploadableFile
{
    private const MAX_BYTES = 10485760;

    public function getCategory(): string {
        return 'document';
    }

    public function getFileFieldName(): string {
        return 'document';
    }

    public function getAllowedMimeTypes(): array {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    public function getMaxBytes(): int {
        return self::MAX_BYTES;
    }

    public function getFolderPath(UploadContext $context): string {
        if ($context->researchId === null) {
            return '/users/' . $context->actorUserId . '/documents';
        }
        return '/research/' . $context->researchId . '/documents';
    }

    public function getSafeFileName(UploadContext $context, UploadedPhpFile $file): string {
        $suffix = $context->researchId === null ? (string) $context->actorUserId : 'r' . $context->researchId;
        return 'document_' . $suffix . '_' . time() . '.bin';
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

