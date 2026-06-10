CREATE TABLE contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    sender_name VARCHAR(255) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    message MEDIUMTEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    status ENUM('new','read','archived','spam') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_contact_messages_tenant_status (tenant_id, status),
    INDEX idx_contact_messages_sender_email (sender_email),
    CONSTRAINT fk_contact_messages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE email_signups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NULL,
    source VARCHAR(120) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    consent_status ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
    confirmed_at TIMESTAMP NULL,
    unsubscribed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_email_signups_tenant_email (tenant_id, email),
    INDEX idx_email_signups_tenant_status (tenant_id, consent_status),
    CONSTRAINT fk_email_signups_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
