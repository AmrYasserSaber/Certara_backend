-- Add ImageKit metadata fields to documents.
-- Idempotent: skip if columns already exist (e.g. when baseline schema.sql already contains them).

SET @has_file_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'documents'
      AND COLUMN_NAME = 'file_id'
);

SET @sql := IF(
    @has_file_id = 0,
    'ALTER TABLE `documents` ADD COLUMN `file_id` VARCHAR(64) NULL AFTER `type`, ADD COLUMN `file_url` VARCHAR(500) NULL AFTER `file_path`, ADD COLUMN `mime_type` VARCHAR(100) NULL AFTER `size_bytes`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

