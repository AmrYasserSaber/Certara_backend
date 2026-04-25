-- Drop triggers that attempted to enforce a single active row.
-- MySQL forbids modifying the same table inside its own trigger (error 1442).

DROP TRIGGER IF EXISTS `trg_user_identity_photos_single_active_ai`;
DROP TRIGGER IF EXISTS `trg_user_identity_photos_single_active_au`;

