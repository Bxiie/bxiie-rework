-- Adds platform-issued tenant signup passcodes and top-bar image defaults.

CREATE TABLE IF NOT EXISTS tenant_signup_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    code_type VARCHAR(32) NOT NULL DEFAULT 'one_time',
    label VARCHAR(190) NULL,
    recipient_email VARCHAR(255) NULL,
    max_redemptions INT UNSIGNED NOT NULL DEFAULT 1,
    redemption_count INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NULL,
    redeemed_by_email VARCHAR(255) NULL,
    redeemed_tenant_id BIGINT UNSIGNED NULL,
    redeemed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_signup_codes_code (code),
    KEY idx_tenant_signup_codes_recipient (recipient_email),
    KEY idx_tenant_signup_codes_status (status),
    KEY idx_tenant_signup_codes_tenant (redeemed_tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_settings (setting_key, setting_value, updated_at)
VALUES ('tenant_signup_code_required', '0', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- End of file.
