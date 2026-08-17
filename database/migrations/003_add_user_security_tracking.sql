ALTER TABLE users
    ADD COLUMN last_login_at DATETIME NULL AFTER active,
    ADD COLUMN failed_login_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at,
    ADD COLUMN last_failed_login_at DATETIME NULL AFTER failed_login_count;
