ALTER TABLE operations_monitor_state
    ADD COLUMN IF NOT EXISTS last_boot_id VARCHAR(190) NULL AFTER last_evening_report_date;

CREATE TABLE IF NOT EXISTS operations_monitor_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id BIGINT UNSIGNED NOT NULL,
    metric_name VARCHAR(190) NOT NULL,
    metric_status ENUM('OK','WARN','CRIT','INFO') NOT NULL,
    expected_value VARCHAR(500) NOT NULL,
    actual_value VARCHAR(500) NOT NULL,
    actual_numeric DECIMAL(24,6) NULL,
    detail_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_operations_monitor_metrics_run (run_id, metric_name),
    INDEX idx_operations_monitor_metrics_name_created (metric_name, created_at),
    INDEX idx_operations_monitor_metrics_status_created (metric_status, created_at),
    CONSTRAINT fk_operations_monitor_metrics_run
        FOREIGN KEY (run_id) REFERENCES operations_monitor_runs(id)
        ON DELETE CASCADE
);
