ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active' AFTER display_name;

UPDATE users SET status = 'active' WHERE status IS NULL;

-- End of file.
