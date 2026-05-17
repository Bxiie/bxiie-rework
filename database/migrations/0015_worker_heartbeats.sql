CREATE TABLE IF NOT EXISTS worker_heartbeats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    worker_name VARCHAR(160) NOT NULL UNIQUE,
    host_name VARCHAR(255) NULL,
    process_id INT NULL,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(40) NOT NULL DEFAULT 'alive',
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_worker_heartbeats_status (status),
    INDEX idx_worker_heartbeats_last_seen (last_seen_at)
);
