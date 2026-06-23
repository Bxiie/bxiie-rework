ALTER TABLE users
    ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'UTC' AFTER display_name;

-- End of file.