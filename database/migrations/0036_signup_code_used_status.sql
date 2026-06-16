-- Normalizes fully redeemed signup codes to the current used status.

UPDATE tenant_signup_codes
SET status = 'used', updated_at = CURRENT_TIMESTAMP
WHERE status = 'redeemed';

-- End of file.
