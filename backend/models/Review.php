<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Review
{
    public static function findActiveLatestByReviewer(int $reviewerId): array
    {
        return Database::fetchAll(
            "SELECT
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
                rv.round_number AS review_round_number,
                rv.status AS review_status,
                rv.decision AS review_decision,
                (
                    SELECT COUNT(*)
                    FROM review_comments rc
                    WHERE rc.review_id = rv.id
                ) AS comment_count
             FROM reviews rv
             INNER JOIN research r ON r.id = rv.research_id
             INNER JOIN (
                SELECT research_id, MAX(round_number) AS max_round_number
                FROM reviews
                GROUP BY research_id
             ) latest ON latest.research_id = rv.research_id AND latest.max_round_number = rv.round_number
             WHERE rv.reviewer_id = ?
               AND rv.status IN ('assigned','in_progress')
             ORDER BY rv.created_at DESC, rv.id DESC",
            [$reviewerId]
        );
    }

    public static function findArchivedLatestByReviewer(int $reviewerId): array
    {
        return Database::fetchAll(
            "SELECT
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
                rv.round_number AS review_round_number,
                rv.status AS review_status,
                rv.decision AS review_decision,
                rv.decided_at AS review_decided_at,
                (
                    SELECT COUNT(*)
                    FROM review_comments rc
                    WHERE rc.review_id = rv.id
                ) AS comment_count
             FROM reviews rv
             INNER JOIN research r ON r.id = rv.research_id
             INNER JOIN (
                SELECT research_id, MAX(round_number) AS max_round_number
                FROM reviews
                GROUP BY research_id
             ) latest ON latest.research_id = rv.research_id AND latest.max_round_number = rv.round_number
             WHERE rv.reviewer_id = ?
               AND rv.decision IN ('approved','rejected')
             ORDER BY rv.decided_at DESC, rv.id DESC",
            [$reviewerId]
        );
    }

    public static function findByResearchAndReviewer(int $researchId, int $reviewerId): array|false
    {
        $row = Database::fetchOne(
            'SELECT * FROM reviews WHERE research_id = ? AND reviewer_id = ? ORDER BY round_number DESC, id DESC LIMIT 1',
            [$researchId, $reviewerId]
        );

        return $row ?? false;
    }

    public static function findLatestByResearch(int $researchId): array|false
    {
        $row = Database::fetchOne(
            'SELECT * FROM reviews WHERE research_id = ? ORDER BY round_number DESC, id DESC LIMIT 1',
            [$researchId]
        );

        return $row ?? false;
    }

    public static function findRoundsByResearch(int $researchId): array
    {
        return Database::fetchAll(
            'SELECT * FROM reviews WHERE research_id = ? ORDER BY round_number ASC, id ASC',
            [$researchId]
        );
    }

    public static function createNextRound(int $researchId, int $reviewerId): array|false
    {
        $latest = self::findLatestByResearch($researchId);
        $nextRound = $latest !== false ? ((int) ($latest['round_number'] ?? 0)) + 1 : 1;
        $previousReviewId = $latest !== false ? (int) ($latest['id'] ?? 0) : null;

        $affected = Database::execute(
            'INSERT INTO reviews (research_id, reviewer_id, round_number, previous_review_id, status, decision, decided_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [$researchId, $reviewerId, $nextRound, $previousReviewId, 'assigned']
        );

        if ($affected <= 0) {
            return false;
        }

        $row = Database::fetchOne('SELECT * FROM reviews WHERE id = LAST_INSERT_ID() LIMIT 1');
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
                rv.round_number AS review_round_number,
                rv.status AS review_status,
                rv.decision AS review_decision,
                (
                    SELECT COUNT(*)
                    FROM review_comments rc
                    WHERE rc.review_id = rv.id
                ) AS comment_count
             FROM reviews rv
             INNER JOIN research r ON r.id = rv.research_id
             WHERE rv.reviewer_id = ?
             ORDER BY rv.created_at DESC, rv.id DESC',
            [$reviewerId]
        );
    }

    public static function findByResearch(int $researchId): array|false
    {
        $row = Database::fetchOne(
            'SELECT * FROM reviews WHERE research_id = ? ORDER BY round_number DESC, id DESC LIMIT 1',
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
