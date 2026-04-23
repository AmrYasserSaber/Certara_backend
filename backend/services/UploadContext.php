<?php

declare(strict_types=1);

namespace App\Services;

final class UploadContext
{
    public function __construct(
        public readonly int $actorUserId,
        public readonly ?int $researchId = null,
    ) {
    }
}

