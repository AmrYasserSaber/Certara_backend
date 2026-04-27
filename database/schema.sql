SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- users --------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(150)    NOT NULL,
    `email`                VARCHAR(190)    NOT NULL,
    `password_hash`        VARCHAR(255)    NOT NULL,
    `phone`                VARCHAR(30)     NULL,
    `national_id`          VARCHAR(30)     NULL,
    `department`           VARCHAR(150)    NULL,
    `faculty`              VARCHAR(150)    NULL,
    `specialization`       VARCHAR(150)    NULL,
    `role`                 ENUM('student','admin','sample_size_officer','reviewer','manager')
                           NOT NULL DEFAULT 'student',
    `status`               ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending',
    `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email`       (`email`),
    UNIQUE KEY `uq_users_national_id` (`national_id`),
    KEY `idx_users_role_status`       (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_avatars -------------------------------------------------------------
DROP TABLE IF EXISTS `user_avatars`;
CREATE TABLE `user_avatars` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `file_id`       VARCHAR(64)     NULL,
    `file_path`     VARCHAR(255)    NOT NULL,
    `file_url`      VARCHAR(500)    NULL,
    `original_name` VARCHAR(255)    NOT NULL,
    `size_bytes`    INT UNSIGNED    NULL,
    `mime_type`     VARCHAR(100)    NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_avatars_user_active` (`user_id`, `is_active`),
    CONSTRAINT `fk_user_avatars_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_identity_photos -----------------------------------------------------
DROP TABLE IF EXISTS `user_identity_photos`;
CREATE TABLE `user_identity_photos` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `type`          ENUM('front','back') NOT NULL,
    `file_id`       VARCHAR(64)     NULL,
    `file_path`     VARCHAR(255)    NOT NULL,
    `file_url`      VARCHAR(500)    NULL,
    `original_name` VARCHAR(255)    NOT NULL,
    `size_bytes`    INT UNSIGNED    NULL,
    `mime_type`     VARCHAR(100)    NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_identity_photos_user_type_active` (`user_id`, `type`, `is_active`),
    CONSTRAINT `fk_user_identity_photos_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- research -----------------------------------------------------------------
DROP TABLE IF EXISTS `research`;
CREATE TABLE `research` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`             BIGINT UNSIGNED NOT NULL,
    `title`                  VARCHAR(255)    NOT NULL,
    `principal_investigator` VARCHAR(150)    NOT NULL,
    `co_investigators`       TEXT            NULL,
    `department`             VARCHAR(150)    NULL,
    `faculty`                VARCHAR(150)    NULL,
    `specialization`         VARCHAR(150)    NULL,
    `serial_number`          VARCHAR(50)     NULL,
    `status`                 ENUM(
                                 'draft','pending_activation','awaiting_payment_1',
                                 'awaiting_sample_size','awaiting_payment_2',
                                 'in_review','revision_requested','reviewer_approved',
                                 'manager_reviewing','approved','rejected'
                             ) NOT NULL DEFAULT 'draft',
    `created_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_research_serial` (`serial_number`),
    KEY `idx_research_student`      (`student_id`),
    KEY `idx_research_status`       (`status`),
    KEY `idx_research_department`   (`department`),
    CONSTRAINT `fk_research_student`
        FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- documents ----------------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_id`   BIGINT UNSIGNED NOT NULL,
    `type`          ENUM(
                        'protocol','application','coi','checklist',
                        'pi_consent','patient_consent','photos_biopsies_consent'
                    ) NOT NULL,
    `file_path`     VARCHAR(255)    NOT NULL,
    `original_name` VARCHAR(255)    NOT NULL,
    `size_bytes`    INT UNSIGNED    NULL,
    `uploaded_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documents_research`      (`research_id`),
    KEY `idx_documents_research_type` (`research_id`, `type`),
    CONSTRAINT `fk_documents_research`
        FOREIGN KEY (`research_id`) REFERENCES `research` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payments -----------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_id`   BIGINT UNSIGNED NOT NULL,
    `amount`        DECIMAL(10,2)   NOT NULL,
    `currency`      CHAR(3)         NOT NULL DEFAULT 'EGP',
    `type`          ENUM('first','second') NOT NULL,
    `status`        ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    `gateway`       VARCHAR(50)     NOT NULL DEFAULT 'cashair',
    `gateway_ref`   VARCHAR(100)    NULL,
    `checkout_url`  VARCHAR(500)    NULL,
    `paid_at`       TIMESTAMP       NULL DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_payments_research` (`research_id`),
    KEY `idx_payments_status`   (`status`),
    UNIQUE KEY `uq_payments_gateway_ref` (`gateway_ref`),
    CONSTRAINT `fk_payments_research`
        FOREIGN KEY (`research_id`) REFERENCES `research` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sample_sizes -------------------------------------------------------------
DROP TABLE IF EXISTS `sample_sizes`;
CREATE TABLE `sample_sizes` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_id`     BIGINT UNSIGNED NOT NULL,
    `officer_id`      BIGINT UNSIGNED NOT NULL,
    `calculated_size` INT UNSIGNED    NOT NULL,
    `notes`           TEXT            NULL,
    `fee_amount`      DECIMAL(10,2)   NOT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sample_sizes_research` (`research_id`),
    KEY `idx_sample_sizes_officer`        (`officer_id`),
    CONSTRAINT `fk_sample_sizes_research`
        FOREIGN KEY (`research_id`) REFERENCES `research` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sample_sizes_officer`
        FOREIGN KEY (`officer_id`)  REFERENCES `users` (`id`)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- reviews ------------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_id` BIGINT UNSIGNED NOT NULL,
    `reviewer_id` BIGINT UNSIGNED NOT NULL,
    `round_number` INT UNSIGNED    NOT NULL,
    `previous_review_id` BIGINT UNSIGNED NULL,
    `status`      ENUM('assigned','in_progress','decided') NOT NULL DEFAULT 'assigned',
    `decision`    ENUM('approved','rejected','revision_requested') NULL,
    `decided_at`  TIMESTAMP       NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reviews_research` (`research_id`),
    KEY `idx_reviews_reviewer` (`reviewer_id`),
    KEY `idx_reviews_research_reviewer_round` (`research_id`, `reviewer_id`, `round_number`),
    UNIQUE KEY `uq_reviews_research_round` (`research_id`, `round_number`),
    CONSTRAINT `fk_reviews_research`
        FOREIGN KEY (`research_id`) REFERENCES `research` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`)    ON DELETE RESTRICT,
    CONSTRAINT `fk_reviews_previous_review`
        FOREIGN KEY (`previous_review_id`) REFERENCES `reviews` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- review_comments ----------------------------------------------------------
DROP TABLE IF EXISTS `review_comments`;
CREATE TABLE `review_comments` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `review_id`    BIGINT UNSIGNED NOT NULL,
    `reviewer_id`  BIGINT UNSIGNED NOT NULL,
    `comment_text` TEXT            NOT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_review_comments_review` (`review_id`),
    CONSTRAINT `fk_review_comments_review`
        FOREIGN KEY (`review_id`)   REFERENCES `reviews` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_comments_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- certificates -------------------------------------------------------------
DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_id`        BIGINT UNSIGNED NOT NULL,
    `issued_by`          BIGINT UNSIGNED NOT NULL,
    `certificate_number` VARCHAR(80)     NOT NULL,
    `file_path`          VARCHAR(255)    NOT NULL,
    `issued_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_certificates_research` (`research_id`),
    UNIQUE KEY `uq_certificates_number`   (`certificate_number`),
    KEY `idx_certificates_issued_by`      (`issued_by`),
    CONSTRAINT `fk_certificates_research`
        FOREIGN KEY (`research_id`) REFERENCES `research` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_certificates_issued_by`
        FOREIGN KEY (`issued_by`)   REFERENCES `users` (`id`)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- notifications ------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `type`        VARCHAR(60)     NOT NULL,
    `title`       VARCHAR(255)    NOT NULL,
    `message`     TEXT            NOT NULL,
    `is_read`     TINYINT(1)      NOT NULL DEFAULT 0,
    `research_id` BIGINT UNSIGNED NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_read` (`user_id`, `is_read`),
    KEY `idx_notifications_research`  (`research_id`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_research`
        FOREIGN KEY (`research_id`) REFERENCES `research` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- refresh_tokens — JWT refresh rotation (DEV 1 owns usage) ----------------
DROP TABLE IF EXISTS `refresh_tokens`;
CREATE TABLE `refresh_tokens` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `jti`        CHAR(32)        NOT NULL,
    `expires_at` TIMESTAMP       NOT NULL,
    `revoked_at` TIMESTAMP       NULL DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_refresh_tokens_jti` (`jti`),
    KEY `idx_refresh_tokens_user`      (`user_id`),
    CONSTRAINT `fk_refresh_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- activity_logs ------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id`    BIGINT UNSIGNED NULL,
    `action`      VARCHAR(100)    NOT NULL,
    `target_type` VARCHAR(60)     NULL,
    `target_id`   BIGINT UNSIGNED NULL,
    `details`     JSON            NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `user_agent`  VARCHAR(255)    NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_actor`   (`actor_id`),
    KEY `idx_activity_logs_target`  (`target_type`, `target_id`),
    KEY `idx_activity_logs_action`  (`action`),
    CONSTRAINT `fk_activity_logs_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
