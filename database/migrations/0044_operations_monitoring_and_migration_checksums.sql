ALTER TABLE schema_migrations
    ADD COLUMN IF NOT EXISTS checksum_sha256 CHAR(64) NULL AFTER migration;

CREATE TABLE IF NOT EXISTS operations_monitor_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_name VARCHAR(255) NOT NULL,
    overall_status ENUM('OK','WARN','CRIT') NOT NULL,
    metric_count INT UNSIGNED NOT NULL DEFAULT 0,
    warning_count INT UNSIGNED NOT NULL DEFAULT 0,
    critical_count INT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    report_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_operations_monitor_runs_created (created_at),
    INDEX idx_operations_monitor_runs_status_created (overall_status, created_at)
);

CREATE TABLE IF NOT EXISTS operations_monitor_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    last_status VARCHAR(16) NOT NULL DEFAULT 'UNKNOWN',
    last_fingerprint CHAR(64) NOT NULL DEFAULT '',
    last_alert_at TIMESTAMP NULL,
    last_morning_report_date DATE NULL,
    last_evening_report_date DATE NULL,
    updated_at TIMESTAMP NULL
);

INSERT INTO operations_monitor_state (id, last_status, last_fingerprint, updated_at)
VALUES (1, 'UNKNOWN', '', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE id = VALUES(id);
