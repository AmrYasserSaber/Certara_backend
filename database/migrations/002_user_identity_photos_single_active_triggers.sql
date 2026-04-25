-- Enforce a single active identity photo per (user_id,type) at the DB layer.
-- This matches the trigger definitions in database/schema.sql.

DROP TRIGGER IF EXISTS `trg_user_identity_photos_single_active_ai`;
DROP TRIGGER IF EXISTS `trg_user_identity_photos_single_active_au`;

CREATE TRIGGER `trg_user_identity_photos_single_active_ai`
AFTER INSERT ON `user_identity_photos`
FOR EACH ROW
BEGIN
    IF NEW.`is_active` = 1 THEN
        UPDATE `user_identity_photos`
        SET `is_active` = 0
        WHERE `user_id` = NEW.`user_id`
          AND `type` = NEW.`type`
          AND `id` <> NEW.`id`
          AND `is_active` = 1;
    END IF;
END;

CREATE TRIGGER `trg_user_identity_photos_single_active_au`
AFTER UPDATE ON `user_identity_photos`
FOR EACH ROW
BEGIN
    IF NEW.`is_active` = 1 AND (OLD.`is_active` <> NEW.`is_active`) THEN
        UPDATE `user_identity_photos`
        SET `is_active` = 0
        WHERE `user_id` = NEW.`user_id`
          AND `type` = NEW.`type`
          AND `id` <> NEW.`id`
          AND `is_active` = 1;
    END IF;
END;

