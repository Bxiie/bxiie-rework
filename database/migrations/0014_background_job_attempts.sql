CREATE TABLE IF NOT EXISTS background_job_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    background_job_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    message TEXT NULL,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_background_job_attempts_job (background_job_id),
    CONSTRAINT fk_background_job_attempts_job FOREIGN KEY (background_job_id) REFERENCES background_jobs(id)
);
