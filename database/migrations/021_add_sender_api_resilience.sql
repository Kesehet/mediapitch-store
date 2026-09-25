CREATE TABLE IF NOT EXISTS sender_api_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    cooldown_until DATETIME NULL,
    rate_limit_limit INT UNSIGNED NULL,
    rate_limit_remaining INT UNSIGNED NULL,
    rate_limit_reset_at DATETIME NULL,
    last_status SMALLINT UNSIGNED NULL,
    last_error VARCHAR(500) NULL,
    last_request_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sender_api_state(id) VALUES(1);

CREATE TABLE IF NOT EXISTS sender_resource_cache (
    cache_key VARCHAR(190) NOT NULL PRIMARY KEY,
    payload LONGTEXT NOT NULL,
    synced_at DATETIME NOT NULL,
    KEY idx_sender_resource_cache_synced (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
