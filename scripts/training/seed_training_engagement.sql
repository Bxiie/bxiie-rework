-- Seeds fictional events, contact messages, and email signups for the ArtsFolio training tenant.
-- Target schema: ArtsFolio repository snapshot dated 2026-07-10.
-- Safe to rerun: prior fixture rows are removed or updated before insertion.
-- Run only against a non-production training tenant.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

START TRANSACTION;

-- Resolve the tenant by slug rather than relying on a hard-coded tenant ID.
SET @training_tenant_slug := 'training';
SET @training_tenant_id := (
    SELECT id
    FROM tenants
    WHERE slug = @training_tenant_slug
    LIMIT 1
);

-- Abort cleanly when the training tenant does not exist.
DROP PROCEDURE IF EXISTS assert_training_tenant_exists;
DELIMITER //
CREATE PROCEDURE assert_training_tenant_exists()
BEGIN
    IF @training_tenant_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Training tenant not found. Create a tenant with slug training before running this seed.';
    END IF;
END//
DELIMITER ;
CALL assert_training_tenant_exists();
DROP PROCEDURE assert_training_tenant_exists;

-- Remove only this script's prior event fixtures so reruns remain deterministic.
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

-- Seed event records. The current schema stores public-facing date text in exhibition_date.
INSERT INTO exhibitions (
    uuid,
    tenant_id,
    exhibition_date,
    name,
    exhibition_type,
    location,
    city,
    state_region,
    work_name,
    notes,
    sort_order,
    status,
    created_at,
    updated_at
) VALUES
(
    UUID(), @training_tenant_id, 'June 20 - September 7, 2026',
    'Northstar: Recent Sculpture', 'Solo exhibition', 'Cedar Line Gallery',
    'Woodstock', 'Vermont', 'Meridian No. 3; Counterweight; Folded Horizon',
    'A focused presentation of recent geometric sculpture. Public URL: https://training.artsfol.io/events/northstar-recent-sculpture',
    10, 'active', UTC_TIMESTAMP() - INTERVAL 45 DAY, UTC_TIMESTAMP()
),
(
    UUID(), @training_tenant_id, 'August 14 - October 4, 2026',
    'Summer Group Exhibition', 'Group exhibition', 'Granite House Arts',
    'Manchester', 'Vermont', 'Quiet Vector; Field Notes I',
    'A regional exhibition examining pattern, structure, and repetition. Public URL: https://training.artsfol.io/events/summer-group-exhibition',
    30, 'active', UTC_TIMESTAMP() - INTERVAL 35 DAY, UTC_TIMESTAMP()
),
(
    UUID(), @training_tenant_id, 'September 19-20, 2026',
    'Open Studio Weekend', 'Open studio', 'Northstar Studio',
    'Perkinsville', 'Vermont', NULL,
    'Studio demonstrations, recent work, and informal conversations with the artist. Public URL: https://training.artsfol.io/events/open-studio-weekend',
    20, 'active', UTC_TIMESTAMP() - INTERVAL 28 DAY, UTC_TIMESTAMP()
),
(
    UUID(), @training_tenant_id, 'October 8, 2026',
    'Artist Talk: Structure and Balance', 'Artist talk', 'Cedar Line Gallery',
    'Woodstock', 'Vermont', 'Meridian No. 3',
    'An evening conversation about geometric systems, fabrication, and material balance. Public URL: https://training.artsfol.io/events/artist-talk-structure-balance',
    40, 'active', UTC_TIMESTAMP() - INTERVAL 20 DAY, UTC_TIMESTAMP()
),
(
    UUID(), @training_tenant_id, 'December 6, 2025 - January 18, 2026',
    'Winter Salon', 'Group exhibition', 'Juniper Room',
    'Brattleboro', 'Vermont', 'Blue Interval',
    'Annual winter exhibition of small works. Public URL: https://training.artsfol.io/events/winter-salon',
    50, 'active', UTC_TIMESTAMP() - INTERVAL 210 DAY, UTC_TIMESTAMP() - INTERVAL 175 DAY
),
(
    UUID(), @training_tenant_id, 'May 3 - November 2, 2025',
    'Vermont Sculpture Walk', 'Outdoor exhibition', 'Riverbend Art Grounds',
    'Windsor', 'Vermont', 'River Geometry',
    'Seasonal outdoor sculpture installation along the river path. Public URL: https://training.artsfol.io/events/vermont-sculpture-walk',
    60, 'active', UTC_TIMESTAMP() - INTERVAL 430 DAY, UTC_TIMESTAMP() - INTERVAL 250 DAY
),
(
    UUID(), @training_tenant_id, 'March 2024',
    'Museum Collection Acquisition', 'Collection milestone', 'North Valley Museum of Art',
    'Montpelier', 'Vermont', 'Folded Horizon',
    'Folded Horizon entered the museum permanent collection. Public URL: https://training.artsfol.io/events/museum-collection-acquisition',
    70, 'active', UTC_TIMESTAMP() - INTERVAL 850 DAY, UTC_TIMESTAMP() - INTERVAL 850 DAY
),
(
    UUID(), @training_tenant_id, 'January - March 2027',
    'Proposed Residency', 'Residency', 'Stonebridge Arts Center',
    'North Adams', 'Massachusetts', NULL,
    'Draft training record for demonstrating editing, publication status, and ordering. Public URL: https://training.artsfol.io/events/proposed-residency',
    5, 'hidden', UTC_TIMESTAMP() - INTERVAL 5 DAY, UTC_TIMESTAMP()
);

-- Remove only the known fictional contact fixtures before recreating them.
DELETE FROM contact_messages
WHERE tenant_id = @training_tenant_id
  AND sender_email IN (
      'training-buyer+taylor@example.com',
      'training-curator+jordan@example.com',
      'training-visitor+sam@example.com'
  );

-- Seed contact messages with varied workflow states and location metadata.
INSERT INTO contact_messages (
    tenant_id,
    sender_name,
    sender_email,
    name,
    email,
    subject,
    message,
    ip_address,
    user_agent,
    country,
    region,
    city,
    status,
    created_at,
    updated_at
) VALUES
(
    @training_tenant_id,
    'Taylor Reed', 'training-buyer+taylor@example.com',
    'Taylor Reed', 'training-buyer+taylor@example.com',
    'Question about Meridian No. 3',
    'Is Meridian No. 3 available for viewing before purchase? I am also interested in the packed dimensions and estimated delivery time to Boston.',
    '192.0.2.41', 'ArtsFolio Training Fixture/1.0',
    'United States', 'Massachusetts', 'Boston',
    'new', UTC_TIMESTAMP() - INTERVAL 2 HOUR, UTC_TIMESTAMP() - INTERVAL 2 HOUR
),
(
    @training_tenant_id,
    'Jordan Lee', 'training-curator+jordan@example.com',
    'Jordan Lee', 'training-curator+jordan@example.com',
    'Group exhibition inquiry',
    'We are planning a group exhibition on constructed form and would like to discuss including two works from Northstar Studio.',
    '198.51.100.27', 'ArtsFolio Training Fixture/1.0',
    'United States', 'New York', 'Albany',
    'read', UTC_TIMESTAMP() - INTERVAL 3 DAY, UTC_TIMESTAMP() - INTERVAL 2 DAY
),
(
    @training_tenant_id,
    'Sam Rivera', 'training-visitor+sam@example.com',
    'Sam Rivera', 'training-visitor+sam@example.com',
    'Open studio hours',
    'Are appointments available on weekday afternoons?',
    '203.0.113.19', 'ArtsFolio Training Fixture/1.0',
    'United States', 'Vermont', 'Rutland',
    'archived', UTC_TIMESTAMP() - INTERVAL 12 DAY, UTC_TIMESTAMP() - INTERVAL 8 DAY
);

-- Seed or refresh mailing-list fixtures using the tenant/email unique key.
INSERT INTO email_signups (
    tenant_id,
    email,
    name,
    source,
    notes,
    ip_address,
    user_agent,
    country,
    region,
    city,
    consent_status,
    confirmed_at,
    unsubscribed_at,
    created_at,
    updated_at
) VALUES
(
    @training_tenant_id,
    'training-list+one@example.com', 'Alex Morgan', 'footer_signup',
    'Confirmed training subscriber created by seed_training_engagement.sql.',
    '192.0.2.51', 'ArtsFolio Training Fixture/1.0',
    'United States', 'Vermont', 'Burlington',
    'confirmed', UTC_TIMESTAMP() - INTERVAL 20 DAY, NULL,
    UTC_TIMESTAMP() - INTERVAL 20 DAY, UTC_TIMESTAMP() - INTERVAL 20 DAY
),
(
    @training_tenant_id,
    'training-list+two@example.com', 'Jamie Chen', 'contact_page',
    'Confirmed training subscriber created by seed_training_engagement.sql.',
    '198.51.100.52', 'ArtsFolio Training Fixture/1.0',
    'United States', 'Massachusetts', 'Northampton',
    'confirmed', UTC_TIMESTAMP() - INTERVAL 9 DAY, NULL,
    UTC_TIMESTAMP() - INTERVAL 9 DAY, UTC_TIMESTAMP() - INTERVAL 9 DAY
),
(
    @training_tenant_id,
    'training-list+pending@example.com', 'Riley Brooks', 'mailing_list_dialog',
    'Pending confirmation fixture for the training video.',
    '203.0.113.53', 'ArtsFolio Training Fixture/1.0',
    'United States', 'New Hampshire', 'Lebanon',
    'pending', NULL, NULL,
    UTC_TIMESTAMP() - INTERVAL 1 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY
),
(
    @training_tenant_id,
    'training-list+duplicate@example.com', 'Cameron Wells', 'footer_signup',
    'Represents an address submitted more than once; the unique tenant/email key retains one row.',
    '192.0.2.54', 'ArtsFolio Training Fixture/1.0',
    'United States', 'Vermont', 'Springfield',
    'confirmed', UTC_TIMESTAMP() - INTERVAL 5 DAY, NULL,
    UTC_TIMESTAMP() - INTERVAL 6 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    source = VALUES(source),
    notes = VALUES(notes),
    ip_address = VALUES(ip_address),
    user_agent = VALUES(user_agent),
    country = VALUES(country),
    region = VALUES(region),
    city = VALUES(city),
    consent_status = VALUES(consent_status),
    confirmed_at = VALUES(confirmed_at),
    unsubscribed_at = VALUES(unsubscribed_at),
    updated_at = VALUES(updated_at);

COMMIT;

-- Verification summary.
SELECT id, slug, name, status
FROM tenants
WHERE id = @training_tenant_id;

SELECT id, exhibition_date, name, exhibition_type, location, city, state_region, status, sort_order
FROM exhibitions
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
  )
ORDER BY sort_order, id;

SELECT id, created_at, sender_name, sender_email, subject, status, city, region
FROM contact_messages
WHERE tenant_id = @training_tenant_id
  AND sender_email LIKE 'training-%@example.com'
ORDER BY created_at DESC, id DESC;

SELECT id, created_at, email, name, source, consent_status, confirmed_at, city, region
FROM email_signups
WHERE tenant_id = @training_tenant_id
  AND email LIKE 'training-list+%@example.com'
ORDER BY created_at DESC, id DESC;

-- End of file.
