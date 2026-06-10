CREATE TABLE rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(255) NOT NULL,
    window_starts_at TIMESTAMP NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_rate_limits_key_window (rate_key, window_starts_at),
    INDEX idx_rate_limits_key (rate_key),
    INDEX idx_rate_limits_window (window_starts_at)
);
