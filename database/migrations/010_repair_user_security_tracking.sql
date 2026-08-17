ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER active,
    ADD COLUMN IF NOT EXISTS failed_login_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at,
    ADD COLUMN IF NOT EXISTS last_failed_login_at DATETIME NULL AFTER failed_login_count;
