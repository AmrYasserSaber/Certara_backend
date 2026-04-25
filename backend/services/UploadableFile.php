<?php

declare(strict_types=1);

namespace App\Services;

interface UploadableFile {
    public function getCategory(): string;

    public function getFileFieldName(): string;

    /** @return list<string> */
    public function getAllowedMimeTypes(): array;

    public function getMaxBytes(): int;

    public function getFolderPath(UploadContext $context): string;

    public function getSafeFileName(UploadContext $context, UploadedPhpFile $file): string;

    public function isSensitive(): bool;

    /** @return array<int, array<string, string>> */
    public function getTransformations(): array;

    public function getChecksExpression(): ?string;
}
