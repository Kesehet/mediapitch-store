CREATE TABLE IF NOT EXISTS search_queries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    query_text VARCHAR(255) NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    result_count INT NOT NULL DEFAULT 0,
    searched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_search_queries_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_search_queries_date (searched_at),
    INDEX idx_search_queries_category (category_id),
    INDEX idx_search_queries_text (query_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
