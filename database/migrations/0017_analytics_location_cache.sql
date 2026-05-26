-- Caches coarse analytics geolocation by the existing anonymized IP hash.
-- Raw IP addresses are intentionally not stored.

CREATE TABLE IF NOT EXISTS analytics_ip_locations (
    ip_hash CHAR(64) PRIMARY KEY,
    country VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    city VARCHAR(120) NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'unknown',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_analytics_ip_locations_location
    ON analytics_ip_locations (country, region, city);

-- End of file.
