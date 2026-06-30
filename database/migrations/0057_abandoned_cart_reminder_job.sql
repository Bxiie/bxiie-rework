-- Adds the recurring abandoned-cart reminder queue job.
-- The job queues email_outbox rows; SMTP delivery remains the responsibility of the email worker.

CREATE INDEX IF NOT EXISTS idx_sales_carts_abandoned_schedule
    ON sales_carts (status, last_item_added_at, updated_at);

INSERT INTO background_jobs (
    tenant_id,
    job_type,
    payload,
    status,
    attempts,
    available_at,
    created_at
)
SELECT NULL,
       'sales.cart.queue_abandoned_reminders',
       JSON_OBJECT('interval_seconds', 3600, 'limit_per_stage', 200),
       'queued',
       0,
       CURRENT_TIMESTAMP,
       CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1
    FROM background_jobs
    WHERE job_type = 'sales.cart.queue_abandoned_reminders'
      AND status IN ('queued', 'running')
);

-- End of file.
