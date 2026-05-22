-- Adds optional entity linkage to tenant analytics events.
-- This lets stats aggregate artwork/image views without scraping IDs from paths.

ALTER TABLE analytics_events
    ADD COLUMN entity_type varchar(80) NULL AFTER event_type,
    ADD COLUMN entity_id bigint(20) unsigned NULL AFTER entity_type;

CREATE INDEX idx_analytics_events_entity
    ON analytics_events (tenant_id, entity_type, entity_id, created_at);

-- End of file.
