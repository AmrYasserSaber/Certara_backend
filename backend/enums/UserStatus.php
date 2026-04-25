<?php

declare(strict_types=1);

namespace App\Enums;

final class UserStatus
{
    public const PENDING  = 'pending';
    public const ACTIVE   = 'active';
    public const REJECTED = 'rejected';

    public const ALL = [self::PENDING, self::ACTIVE, self::REJECTED];
}
