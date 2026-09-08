ALTER TABLE newsletter_subscribers
    ADD COLUMN validation_status VARCHAR(20) NULL AFTER source,
    ADD COLUMN validation_reason VARCHAR(255) NULL AFTER validation_status,
    ADD COLUMN validation_checked_at DATETIME NULL AFTER validation_reason,
    ADD KEY idx_newsletter_validation_status (validation_status);