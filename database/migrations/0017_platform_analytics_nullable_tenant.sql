ALTER TABLE analytics_events
    DROP FOREIGN KEY fk_analytics_events_tenant;

ALTER TABLE analytics_events
    MODIFY tenant_id BIGINT UNSIGNED NULL;

ALTER TABLE analytics_events
    ADD CONSTRAINT fk_analytics_events_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id);

CREATE INDEX idx_analytics_events_platform_created
    ON analytics_events (tenant_id, event_type, created_at);

-- End of file.
