CREATE TABLE user_identities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    identity_type ENUM('local_password','oauth_oidc') NOT NULL,
    provider VARCHAR(80) NOT NULL,
    provider_subject VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    display_name VARCHAR(255) NULL,
    metadata JSON NULL,
    verified_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_user_identity_provider_subject (provider, provider_subject),
    INDEX idx_user_identities_user (user_id),
    INDEX idx_user_identities_email (email),
    CONSTRAINT fk_user_identities_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE tenant_memberships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','invited','disabled','removed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_tenant_membership (tenant_id, user_id),
    INDEX idx_tenant_memberships_user (user_id),
    CONSTRAINT fk_tenant_memberships_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_tenant_memberships_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('platform','tenant') NOT NULL,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_scope_slug (scope, slug)
);

CREATE TABLE role_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_assignment (role_id, user_id, tenant_id),
    INDEX idx_role_assignments_user (user_id),
    INDEX idx_role_assignments_tenant (tenant_id),
    CONSTRAINT fk_role_assignments_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_role_assignments_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_role_assignments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE email_verification_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_verification_tokens_user FOREIGN KEY (user_id) REFERENCES users(id)
);
