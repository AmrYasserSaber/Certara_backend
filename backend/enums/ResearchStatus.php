<?php

declare(strict_types=1);

namespace App\Enums;

final class ResearchStatus
{
    public const DRAFT                = 'draft';
    public const PENDING_ACTIVATION   = 'pending_activation';
    public const AWAITING_PAYMENT_1   = 'awaiting_payment_1';
    public const AWAITING_SAMPLE_SIZE = 'awaiting_sample_size';
    public const AWAITING_PAYMENT_2   = 'awaiting_payment_2';
    public const IN_REVIEW            = 'in_review';
    public const REVISION_REQUESTED   = 'revision_requested';
    public const REVIEWER_APPROVED    = 'reviewer_approved';
    public const MANAGER_REVIEWING    = 'manager_reviewing';
    public const APPROVED             = 'approved';
    public const REJECTED             = 'rejected';

    public const ALL = [
        self::DRAFT,
        self::PENDING_ACTIVATION,
        self::AWAITING_PAYMENT_1,
        self::AWAITING_SAMPLE_SIZE,
        self::AWAITING_PAYMENT_2,
        self::IN_REVIEW,
        self::REVISION_REQUESTED,
        self::REVIEWER_APPROVED,
        self::MANAGER_REVIEWING,
        self::APPROVED,
        self::REJECTED,
    ];
}
