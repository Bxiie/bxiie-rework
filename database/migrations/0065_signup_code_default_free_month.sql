-- Defaults newly inserted signup codes to one complimentary month.

ALTER TABLE tenant_signup_codes
    MODIFY COLUMN free_access_months INT UNSIGNED NOT NULL DEFAULT 1;

-- End of file.
