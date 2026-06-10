ALTER TABLE background_jobs
    MODIFY COLUMN status ENUM('queued','running','complete','failed','cancelled') NOT NULL DEFAULT 'queued';
