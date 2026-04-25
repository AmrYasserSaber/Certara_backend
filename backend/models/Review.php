<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Review
{
    public static function findByResearchAndReviewer(int $researchId, int $reviewerId): array|false
    {
        $row = Database::fetchOne(
            'SELECT * FROM reviews WHERE research_id = ? AND reviewer_id = ? LIMIT 1',
            [$researchId, $reviewerId]
        );

        return $row ?? false;
    }

    public static function findByReviewer(int $reviewerId): array
    {
        return Database::fetchAll(
            'SELECT
                r.id,
                r.title,
                r.principal_investigator,
                r.co_investigators,
                r.department,
                r.faculty,
                r.serial_number,
                r.status,
                r.created_at,
                rv.id AS review_id,
                rv.status AS review_status,
                rv.decision AS review_decision,
                (
                    SELECT COUNT(*)
                    FROM review_comments rc
                    WHERE rc.review_id = rv.id
                      AND rc.reviewer_id <> ?
                ) AS unread_comment_count
             FROM reviews rv
             INNER JOIN research r ON r.id = rv.research_id
             WHERE rv.reviewer_id = ?
             ORDER BY rv.created_at DESC',
            [$reviewerId, $reviewerId]
        );
    }

    public static function findByResearch(int $researchId): array|false
    {
        $row = Database::fetchOne(
            'SELECT * FROM reviews WHERE research_id = ? LIMIT 1',
            [$researchId]
        );

        return $row ?? false;
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $affected = Database::execute(
            'UPDATE reviews SET status = ?, updated_at = NOW() WHERE id = ?',
            [$status, $id]
        );

        return $affected > 0;
    }

    public static function updateDecision(int $id, string $decision): bool
    {
        $affected = Database::execute(
            'UPDATE reviews
             SET status = ?, decision = ?, decided_at = NOW(), updated_at = NOW()
             WHERE id = ?',
            ['decided', $decision, $id]
        );

        return $affected > 0;
    }
}
