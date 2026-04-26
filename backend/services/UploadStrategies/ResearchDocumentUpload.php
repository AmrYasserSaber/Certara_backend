<?php

declare(strict_types=1);

namespace App\Services\UploadStrategies;

use App\Services\UploadableFile;
use App\Services\UploadContext;
use App\Services\UploadedPhpFile;

final class ResearchDocumentUpload implements UploadableFile
{
    private const MAX_BYTES = 10485760;

    public function __construct(
        private readonly string $documentType,
    ) {
    }

    public function getCategory(): string {
        return 'research_document';
    }

    public function getFileFieldName(): string {
        return 'document';
    }

    public function getAllowedMimeTypes(): array {
        return [
            'application/pdf',
        ];
    }

    public function getMaxBytes(): int {
        return self::MAX_BYTES;
    }

    public function getFolderPath(UploadContext $context): string {
        $researchId = $context->researchId ?? 0;
        return '/research/' . $researchId . '/documents';
    }

    public function getSafeFileName(UploadContext $context, UploadedPhpFile $file): string {
        $researchId = $context->researchId ?? 0;
        $type = $this->sanitizeDocumentType($this->documentType);
        return 'document_' . $type . '_r' . $researchId . '_' . time() . '.pdf';
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

    private function sanitizeDocumentType(string $documentType): string {
        $documentType = strtolower(trim($documentType));
        $documentType = preg_replace('/[^a-z0-9_]+/', '_', $documentType) ?? '';
        $documentType = trim($documentType, '_');
        if ($documentType === '') {
            return 'document';
        }
        return $documentType;
    }
}

