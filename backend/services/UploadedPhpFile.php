<?php

declare(strict_types=1);

namespace App\Services;

final class UploadedPhpFile
{
    public function __construct(
        public readonly string $fieldName,
        public readonly string $originalName,
        public readonly string $tmpName,
        public readonly int $sizeBytes,
        public readonly int $errorCode,
    ) {
    }
}

