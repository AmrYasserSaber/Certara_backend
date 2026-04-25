<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Certificate persistence helper.
 *
 * This model keeps certificate database access in one place while staying
 * compatible with the lightweight project architecture.
 */
final class Certificate
{
    /**
     * Insert a certificate row and return the persisted record.
     *
     * Expected keys:
     * - research_id
     * - issued_by
     * - certificate_number
     * - file_path
     * - issued_at (optional)
     */
    public static function create(array $data): ?array
    {
        $db = Database::getInstance();
        $researchId = (int) ($data['research_id'] ?? 0);
        $issuedBy = (int) ($data['issued_by'] ?? 0);
        $certificateNumber = trim((string) ($data['certificate_number'] ?? ''));
        $filePath = trim((string) ($data['file_path'] ?? ''));
        $issuedAt = trim((string) ($data['issued_at'] ?? date('Y-m-d H:i:s')));

        if ($researchId <= 0 || $issuedBy <= 0 || $certificateNumber === '' || $filePath === '') {
            return null;
        }

        $db->execute(
            'INSERT INTO certificates (research_id, issued_by, certificate_number, file_path, issued_at) VALUES (?, ?, ?, ?, ?)',
            [$researchId, $issuedBy, $certificateNumber, $filePath, $issuedAt]
        );

        $id = (int) Database::connection()->getPdo()->lastInsertId();
        return $id > 0 ? self::findById($id) : null;
    }

    /**
     * Find a certificate row by the related research ID.
     */
    public static function findByResearchId(int $researchId): ?array
    {
        if ($researchId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id, research_id, issued_by, certificate_number, file_path, issued_at FROM certificates WHERE research_id = ? LIMIT 1',
            [$researchId]
        );

        return $row === null ? null : self::normalize($row);
    }

    /**
     * Find a certificate row by its primary key.
     */
    public static function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id, research_id, issued_by, certificate_number, file_path, issued_at FROM certificates WHERE id = ? LIMIT 1',
            [$id]
        );

        return $row === null ? null : self::normalize($row);
    }

    /**
     * Normalize raw database rows into a predictable associative array.
     */
    private static function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'research_id' => (int) ($row['research_id'] ?? 0),
            'issued_by' => (int) ($row['issued_by'] ?? 0),
            'certificate_number' => (string) ($row['certificate_number'] ?? ''),
            'file_path' => (string) ($row['file_path'] ?? ''),
            'issued_at' => $row['issued_at'] ?? null,
        ];
    }
}
