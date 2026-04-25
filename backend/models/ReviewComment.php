<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ReviewComment
{
    public static function create(array $data): array|false
    {
        $affected = Database::execute(
            'INSERT INTO review_comments (review_id, reviewer_id, comment_text, created_at)
             VALUES (?, ?, ?, NOW())',
            [
                (int) ($data['review_id'] ?? 0),
                (int) ($data['reviewer_id'] ?? 0),
                (string) ($data['comment_text'] ?? ''),
            ]
        );

        if ($affected <= 0) {
            return false;
        }

        $row = Database::fetchOne('SELECT * FROM review_comments WHERE id = LAST_INSERT_ID() LIMIT 1');
        return $row ?? false;
    }

    public static function findByReview(int $reviewId): array
    {
        return Database::fetchAll(
            'SELECT id, review_id, reviewer_id, comment_text, created_at
             FROM review_comments
             WHERE review_id = ?
             ORDER BY created_at ASC',
            [$reviewId]
        );
    }
}
