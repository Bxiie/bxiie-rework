-- Removes only the fictional records created by seed_training_engagement.sql.
-- Run only when intentionally resetting the ArtsFolio training tenant fixtures.

SET NAMES utf8mb4;
START TRANSACTION;

SET @training_tenant_id := (
    SELECT id
    FROM tenants
    WHERE slug = 'training'
    LIMIT 1
);

DELETE FROM exhibitions
WHERE tenant_id = @training_tenant_id
  AND name IN (
      'Northstar: Recent Sculpture',
      'Summer Group Exhibition',
      'Open Studio Weekend',
      'Artist Talk: Structure and Balance',
      'Winter Salon',
      'Vermont Sculpture Walk',
      'Museum Collection Acquisition',
      'Proposed Residency'
  );

DELETE FROM contact_messages
WHERE tenant_id = @training_tenant_id
  AND sender_email IN (
      'training-buyer+taylor@example.com',
      'training-curator+jordan@example.com',
      'training-visitor+sam@example.com'
  );

DELETE FROM email_signups
WHERE tenant_id = @training_tenant_id
  AND email IN (
      'training-list+one@example.com',
      'training-list+two@example.com',
      'training-list+pending@example.com',
      'training-list+duplicate@example.com'
  );

COMMIT;

SELECT
    (SELECT COUNT(*) FROM exhibitions WHERE tenant_id = @training_tenant_id AND name LIKE '%Northstar%') AS remaining_matching_events,
    (SELECT COUNT(*) FROM contact_messages WHERE tenant_id = @training_tenant_id AND sender_email LIKE 'training-%@example.com') AS remaining_training_messages,
    (SELECT COUNT(*) FROM email_signups WHERE tenant_id = @training_tenant_id AND email LIKE 'training-list+%@example.com') AS remaining_training_signups;

-- End of file.
