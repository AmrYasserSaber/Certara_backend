<?php

declare(strict_types=1);

namespace App\Enums;

final class PaymentStatus
{
    public const PENDING = 'pending';
    public const PAID    = 'paid';
    public const FAILED  = 'failed';

    public const ALL = [self::PENDING, self::PAID, self::FAILED];
}
