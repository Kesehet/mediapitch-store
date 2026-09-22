-- SEO completeness and resilient admin form drafts.

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS seo_title VARCHAR(255) NULL AFTER editorial_notes,
    ADD COLUMN IF NOT EXISTS meta_description VARCHAR(320) NULL AFTER seo_title,
    ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(1000) NULL AFTER meta_description,
    ADD COLUMN IF NOT EXISTS robots_index TINYINT(1) NOT NULL DEFAULT 1 AFTER canonical_url;

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(1000) NULL AFTER meta_description,
    ADD COLUMN IF NOT EXISTS robots_index TINYINT(1) NOT NULL DEFAULT 1 AFTER canonical_url;

ALTER TABLE brands
    ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER logo_url,
    ADD COLUMN IF NOT EXISTS seo_title VARCHAR(255) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS meta_description VARCHAR(320) NULL AFTER seo_title,
    ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(1000) NULL AFTER meta_description,
    ADD COLUMN IF NOT EXISTS robots_index TINYINT(1) NOT NULL DEFAULT 1 AFTER canonical_url;

CREATE TABLE IF NOT EXISTS admin_form_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    draft_key VARCHAR(190) NOT NULL,
    payload_json MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_form_draft_user_key (user_id, draft_key),
    INDEX idx_admin_form_draft_updated (updated_at),
    CONSTRAINT fk_admin_form_draft_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
