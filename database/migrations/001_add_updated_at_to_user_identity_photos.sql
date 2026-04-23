-- Add updated_at to user_identity_photos for auditability.
-- This matches the definition in database/schema.sql.

ALTER TABLE `user_identity_photos`
    ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
    AFTER `created_at`;

