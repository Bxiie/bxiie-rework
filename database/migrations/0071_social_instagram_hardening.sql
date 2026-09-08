-- Hardens social publishing account binding and preserves scheduled-post identity.
-- 0070 may already be applied, so this migration is additive rather than rewriting it.

ALTER TABLE social_connections
    ADD COLUMN IF NOT EXISTS is_default BOOLEAN NOT NULL DEFAULT FALSE AFTER status;

UPDATE social_connections
SET is_default = TRUE
WHERE is_default = FALSE;

ALTER TABLE social_connections
    DROP INDEX uq_social_connection_tenant_provider,
    ADD UNIQUE KEY uq_social_connection_account (tenant_id, provider, external_account_id),
    ADD INDEX idx_social_connection_default (tenant_id, provider, is_default, status);

ALTER TABLE social_posts
    ADD COLUMN IF NOT EXISTS social_connection_id BIGINT UNSIGNED NULL AFTER provider,
    ADD INDEX idx_social_post_connection (social_connection_id),
    ADD CONSTRAINT fk_social_post_connection FOREIGN KEY (social_connection_id) REFERENCES social_connections(id);

UPDATE social_posts sp
JOIN social_connections sc
  ON sc.tenant_id = sp.tenant_id
 AND sc.provider = sp.provider
SET sp.social_connection_id = sc.id
WHERE sp.social_connection_id IS NULL;

-- End of file.
