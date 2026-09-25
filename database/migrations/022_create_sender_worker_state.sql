CREATE TABLE IF NOT EXISTS sender_worker_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    last_started_at DATETIME NULL,
    last_completed_at DATETIME NULL,
    last_success_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    last_transactional_sent INT UNSIGNED NOT NULL DEFAULT 0,
    last_campaign_dispatched INT UNSIGNED NOT NULL DEFAULT 0,
    last_remaining_today INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sender_worker_state(id) VALUES(1);
