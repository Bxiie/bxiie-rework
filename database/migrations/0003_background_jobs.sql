CREATE TABLE background_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    job_type VARCHAR(120) NOT NULL,
    payload JSON NULL,
    status ENUM('queued','running','complete','failed') NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    last_error MEDIUMTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_jobs_status_available (status, available_at),
    INDEX idx_jobs_tenant (tenant_id),
    CONSTRAINT fk_background_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
