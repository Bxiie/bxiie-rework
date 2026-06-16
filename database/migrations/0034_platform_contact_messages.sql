-- Allows public platform contact messages to live in the same workflow table as tenant contacts.
-- Platform contacts are stored with tenant_id = NULL and managed from Platform Admin > Contacts.

ALTER TABLE contact_messages
    MODIFY tenant_id BIGINT UNSIGNED NULL;

ALTER TABLE contact_messages
    ADD INDEX IF NOT EXISTS idx_contact_messages_platform_status (tenant_id, status, created_at);

-- End of file.
