<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SampleSize
{
    public static function create(array $data): array|false
    {
        $affected = Database::execute(
            'INSERT INTO sample_sizes (research_id, officer_id, calculated_size, notes, fee_amount, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [
                (int) ($data['research_id'] ?? 0),
                (int) ($data['officer_id'] ?? 0),
                (int) ($data['calculated_size'] ?? 0),
                $data['notes'] ?? null,
                (float) ($data['fee_amount'] ?? 0),
            ]
        );

        if ($affected <= 0) {
            return false;
        }

        $row = Database::fetchOne('SELECT * FROM sample_sizes WHERE id = LAST_INSERT_ID() LIMIT 1');
        return $row ?? false;
    }

    public static function findByResearch(int $researchId): array|false
    {
        $row = Database::fetchOne(
            'SELECT * FROM sample_sizes WHERE research_id = ? LIMIT 1',
            [$researchId]
        );

        return $row ?? false;
    }
}
