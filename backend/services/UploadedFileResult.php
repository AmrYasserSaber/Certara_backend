<?php

declare(strict_types=1);

namespace App\Services;

final class UploadedFileResult
{
    public function __construct(
        public readonly string $fileId,
        public readonly string $filePath,
        public readonly string $url,
        public readonly string $originalName,
        public readonly int $sizeBytes,
        public readonly string $mimeType,
    ) {
    }
}

