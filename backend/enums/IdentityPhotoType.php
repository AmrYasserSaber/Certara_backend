<?php

declare(strict_types=1);

namespace App\Enums;

final class IdentityPhotoType
{
    public const FRONT = 'front';
    public const BACK = 'back';

    public const ALL = [
        self::FRONT,
        self::BACK,
    ];
}

