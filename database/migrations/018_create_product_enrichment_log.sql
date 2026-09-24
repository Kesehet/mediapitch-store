CREATE TABLE IF NOT EXISTS product_enrichment_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value MEDIUMTEXT NULL,
    new_value MEDIUMTEXT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_url VARCHAR(2048) NULL,
    confidence DECIMAL(5,4) NULL,
    status ENUM('applied','skipped','conflict','failed') NOT NULL DEFAULT 'applied',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_enrichment_product (product_id, created_at),
    INDEX idx_product_enrichment_status (status, created_at),
    CONSTRAINT fk_product_enrichment_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
