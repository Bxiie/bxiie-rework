-- Records Stripe webhook events for idempotent processing and diagnostics.
-- Safe to run after subscription billing migrations.

CREATE TABLE IF NOT EXISTS stripe_webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(255) NOT NULL,
    stripe_object_id VARCHAR(255) NULL,
    payload_hash CHAR(64) NOT NULL,
    payload_json LONGTEXT NULL,
    status ENUM('processing', 'processed', 'failed', 'ignored') NOT NULL DEFAULT 'processing',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
    response_code INT NULL,
    last_error TEXT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_stripe_webhook_events_event_id (event_id),
    KEY idx_stripe_webhook_events_type_received (event_type, received_at),
    KEY idx_stripe_webhook_events_status_received (status, received_at),
    KEY idx_stripe_webhook_events_object (stripe_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of file.
