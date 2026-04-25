<?php

declare(strict_types=1);

namespace App\Enums;

final class Roles
{
    public const STUDENT             = 'student';
    public const ADMIN               = 'admin';
    public const SAMPLE_SIZE_OFFICER = 'sample_size_officer';
    public const REVIEWER            = 'reviewer';
    public const MANAGER             = 'manager';

    public const ALL = [
        self::STUDENT,
        self::ADMIN,
        self::SAMPLE_SIZE_OFFICER,
        self::REVIEWER,
        self::MANAGER,
    ];
}
