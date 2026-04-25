<?php

declare(strict_types=1);

namespace App\Enums;


final class DocumentType
{
    public const PROTOCOL                = 'protocol';
    public const APPLICATION             = 'application';
    public const COI                     = 'coi';
    public const CHECKLIST               = 'checklist';
    public const PI_CONSENT              = 'pi_consent';
    public const PATIENT_CONSENT         = 'patient_consent';
    public const PHOTOS_BIOPSIES_CONSENT = 'photos_biopsies_consent';

    public const ALL = [
        self::PROTOCOL,
        self::APPLICATION,
        self::COI,
        self::CHECKLIST,
        self::PI_CONSENT,
        self::PATIENT_CONSENT,
        self::PHOTOS_BIOPSIES_CONSENT,
    ];
}
