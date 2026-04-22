<?php

declare(strict_types=1);

namespace App\Enums;


final class NotificationType
{
    public const ACCOUNT_ACTIVATED  = 'account_activated';
    public const ACCOUNT_REJECTED   = 'account_rejected';
    public const PAYMENT_CONFIRMED  = 'payment_confirmed';
    public const SAMPLE_SIZE_SET    = 'sample_size_set';
    public const REVIEW_REQUESTED   = 'review_requested';
    public const REVISION_REQUESTED = 'revision_requested';
    public const RESEARCH_APPROVED  = 'research_approved';
    public const RESEARCH_REJECTED  = 'research_rejected';
    public const CERTIFICATE_READY  = 'certificate_ready';
}
