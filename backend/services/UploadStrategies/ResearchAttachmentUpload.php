<?php

declare(strict_types=1);

namespace App\Services\UploadStrategies;

use App\Services\UploadableFile;
use App\Services\UploadContext;
use App\Services\UploadedPhpFile;

final class ResearchAttachmentUpload implements UploadableFile
{
    private const MAX_BYTES = 10485760;

    public function getCategory(): string {
        return 'research_attachment';
    }

    public function getFileFieldName(): string {
        return 'attachment';
    }

    public function getAllowedMimeTypes(): array {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ];
    }

    public function getMaxBytes(): int {
        return self::MAX_BYTES;
    }

    public function getFolderPath(UploadContext $context): string {
        $researchId = $context->researchId ?? 0;
        return '/research/' . $researchId . '/attachments';
    }

    public function getSafeFileName(UploadContext $context, UploadedPhpFile $file): string {
        $researchId = $context->researchId ?? 0;
        return 'attachment_r' . $researchId . '_' . time() . '.bin';
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
