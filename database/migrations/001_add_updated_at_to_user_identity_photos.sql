-- Add updated_at to user_identity_photos for auditability.
-- Idempotent: skip if the column already exists (e.g. when baseline schema.sql
-- already contains updated_at).

SET @has_updated_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_identity_photos'
      AND COLUMN_NAME = 'updated_at'
);

SET @sql := IF(
    @has_updated_at = 0,
    'ALTER TABLE `user_identity_photos` ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

