-- Aggregate analytics into compact dashboard-friendly hourly and daily tables.
-- These tables are safe to recreate while migration 0039 remains unapplied.
DROP TABLE IF EXISTS analytics_rollups_daily;
DROP TABLE IF EXISTS analytics_rollups_hourly;

CREATE TABLE analytics_rollups_hourly (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_start DATETIME NOT NULL,
    tenant_key BIGINT UNSIGNED NOT NULL DEFAULT 0,
    event_type VARCHAR(80) NOT NULL,
    path VARCHAR(500) NOT NULL DEFAULT '',
    entity_type VARCHAR(80) NOT NULL DEFAULT '',
    entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    country VARCHAR(120) NOT NULL DEFAULT '',
    region VARCHAR(120) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    dimension_hash CHAR(64) NOT NULL,
    event_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitor_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    first_event_at DATETIME NULL,
    last_event_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY analytics_rollups_hourly_dimension (bucket_start, tenant_key, event_type, dimension_hash),
    KEY analytics_rollups_hourly_tenant_bucket (tenant_key, bucket_start),
    KEY analytics_rollups_hourly_bucket (bucket_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analytics_rollups_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_date DATE NOT NULL,
    tenant_key BIGINT UNSIGNED NOT NULL DEFAULT 0,
    event_type VARCHAR(80) NOT NULL,
    path VARCHAR(500) NOT NULL DEFAULT '',
    entity_type VARCHAR(80) NOT NULL DEFAULT '',
    entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    country VARCHAR(120) NOT NULL DEFAULT '',
    region VARCHAR(120) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    dimension_hash CHAR(64) NOT NULL,
    event_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitor_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    first_event_at DATETIME NULL,
    last_event_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY analytics_rollups_daily_dimension (bucket_date, tenant_key, event_type, dimension_hash),
    KEY analytics_rollups_daily_tenant_bucket (tenant_key, bucket_date),
    KEY analytics_rollups_daily_bucket (bucket_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS analytics_events_tenant_created_type ON analytics_events (tenant_id, created_at, event_type);
CREATE INDEX IF NOT EXISTS analytics_events_created ON analytics_events (created_at);
CREATE INDEX IF NOT EXISTS analytics_events_entity ON analytics_events (tenant_id, entity_type, entity_id, created_at);

INSERT INTO background_jobs (tenant_id, job_type, payload, status, attempts, available_at, created_at)
SELECT NULL, 'analytics.rollup', '{"days":3}', 'queued', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1
    FROM background_jobs
    WHERE job_type = 'analytics.rollup'
      AND status IN ('queued', 'running')
);

-- End of file.
