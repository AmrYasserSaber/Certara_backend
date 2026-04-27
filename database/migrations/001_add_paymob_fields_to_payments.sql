-- Add Paymob audit fields to payments
-- Idempotent: each column is added only if missing.

SET @table_name := 'payments';

-- paymob_order_id (Paymob order.id)
SET @col := 'paymob_order_id';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = @col
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payments ADD COLUMN paymob_order_id BIGINT UNSIGNED NULL AFTER checkout_url',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- paymob_transaction_id (Paymob transaction obj.id)
SET @col := 'paymob_transaction_id';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = @col
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payments ADD COLUMN paymob_transaction_id BIGINT UNSIGNED NULL AFTER paymob_order_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- paymob_integration_id (the integration_id used)
SET @col := 'paymob_integration_id';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = @col
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payments ADD COLUMN paymob_integration_id BIGINT UNSIGNED NULL AFTER paymob_transaction_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- paymob_method (card|wallet|kiosk)
SET @col := 'paymob_method';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = @col
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payments ADD COLUMN paymob_method VARCHAR(20) NULL AFTER paymob_integration_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- amount_cents_reported (for mismatch investigations)
SET @col := 'amount_cents_reported';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = @col
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payments ADD COLUMN amount_cents_reported INT UNSIGNED NULL AFTER paymob_method',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- paymob_callback_payload (store last verified callback JSON, optional but useful)
SET @col := 'paymob_callback_payload';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = @col
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE payments ADD COLUMN paymob_callback_payload JSON NULL AFTER amount_cents_reported',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Helpful lookup indexes (idempotent)
SET @idx := 'idx_payments_paymob_order_id';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND INDEX_NAME = @idx
);
SET @sql := IF(
    @exists = 0,
    'CREATE INDEX idx_payments_paymob_order_id ON payments (paymob_order_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := 'idx_payments_paymob_transaction_id';
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND INDEX_NAME = @idx
);
SET @sql := IF(
    @exists = 0,
    'CREATE INDEX idx_payments_paymob_transaction_id ON payments (paymob_transaction_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

