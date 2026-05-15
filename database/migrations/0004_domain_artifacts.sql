CREATE TABLE domain_artifacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    artifact_type VARCHAR(120) NOT NULL,
    artifact_body MEDIUMTEXT NOT NULL,
    status ENUM('rendered','approved','written','failed') NOT NULL DEFAULT 'rendered',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    INDEX idx_domain_artifacts_tenant (tenant_id),
    INDEX idx_domain_artifacts_hostname (hostname),
    CONSTRAINT fk_domain_artifacts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
