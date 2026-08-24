CREATE TABLE IF NOT EXISTS ai_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(50) NOT NULL DEFAULT 'content_draft',
    status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
    topic VARCHAR(500) NOT NULL,
    content_type ENUM('blog','buying_guide') NOT NULL DEFAULT 'blog',
    content_id BIGINT UNSIGNED NULL,
    model VARCHAR(150) NULL,
    stage VARCHAR(80) NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    metadata_json LONGTEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_jobs_status_created (status, created_at),
    INDEX idx_ai_jobs_completed (completed_at),
    INDEX idx_ai_jobs_content (content_id),
    CONSTRAINT fk_ai_jobs_content FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_research_sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    query_text VARCHAR(1000) NULL,
    url VARCHAR(2048) NOT NULL,
    title VARCHAR(500) NULL,
    publisher VARCHAR(255) NULL,
    excerpt MEDIUMTEXT NULL,
    retrieved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_source_job_url (job_id, url(500)),
    INDEX idx_ai_sources_job (job_id),
    CONSTRAINT fk_ai_sources_job FOREIGN KEY (job_id) REFERENCES ai_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;