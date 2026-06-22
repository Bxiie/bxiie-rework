-- Support stale recovery and queue-health queries for concurrent workers.
CREATE INDEX idx_background_jobs_status_updated
    ON background_jobs (status, updated_at);

CREATE INDEX idx_email_outbox_status_updated
    ON email_outbox (status, updated_at);

-- End of file.
