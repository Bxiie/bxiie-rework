-- Lets a background job survive a configurable number of transient failures
-- (auto-retried with backoff) before it is marked permanently failed and
-- counted by the queue.jobs.failed health alert. Previously any exception on
-- a job's very first attempt marked it failed immediately.
ALTER TABLE background_jobs
    ADD COLUMN IF NOT EXISTS max_attempts INT UNSIGNED NOT NULL DEFAULT 5 AFTER attempts;

-- End of file.
