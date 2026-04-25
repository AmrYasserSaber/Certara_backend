<?php

declare(strict_types=1);

namespace App\Enums;

final class PaymentType
{
    public const FIRST  = 'first';
    public const SECOND = 'second';

    public const ALL = [self::FIRST, self::SECOND];
}
