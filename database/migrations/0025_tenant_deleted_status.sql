-- Allows soft-deleted tenants to be represented without physical removal.
ALTER TABLE tenants
    MODIFY status ENUM('pending_email','pending_setup','trial','active','suspended','archived','deleted') NOT NULL DEFAULT 'pending_setup';

-- End of file.
