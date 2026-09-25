CREATE TABLE IF NOT EXISTS sender_subscriber_cache (
    email VARCHAR(190) NOT NULL PRIMARY KEY,
    provider_subscriber_id VARCHAR(100) NULL,
    firstname VARCHAR(100) NULL,
    lastname VARCHAR(100) NULL,
    status VARCHAR(40) NULL,
    groups_json LONGTEXT NULL,
    provider_created_at VARCHAR(64) NULL,
    synced_at DATETIME NOT NULL,
    KEY idx_sender_subscriber_cache_status (status),
    KEY idx_sender_subscriber_cache_synced (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sender_subscriber_cache_meta (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    last_synced_at DATETIME NULL,
    refresh_after DATETIME NULL,
    reported_total INT UNSIGNED NOT NULL DEFAULT 0,
    cached_rows INT UNSIGNED NOT NULL DEFAULT 0,
    pages INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sender_subscriber_cache_meta(id) VALUES(1);
