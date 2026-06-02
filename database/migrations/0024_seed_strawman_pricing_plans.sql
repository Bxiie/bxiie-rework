-- Seeds practical strawman pricing rows and disclosures for platform-admin editing.
INSERT INTO plans (slug, name, monthly_price_cents, custom_domain_included, is_active)
VALUES
    ('free', 'Free', 0, FALSE, TRUE),
    ('studio', 'Studio', 900, FALSE, TRUE),
    ('pro', 'Professional', 1900, TRUE, TRUE),
    ('collective', 'Collective', 4900, TRUE, TRUE)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    monthly_price_cents = VALUES(monthly_price_cents),
    custom_domain_included = VALUES(custom_domain_included),
    is_active = VALUES(is_active);

UPDATE plans
SET description = CASE slug
        WHEN 'free' THEN 'For artists trying ArtsFolio or publishing a compact public portfolio. Includes ArtsFolio notification/link on free tenant pages.'
        WHEN 'studio' THEN 'For working artists who need a serious portfolio, contact messaging, email subscribers, analytics, and enough room for an active body of work.'
        WHEN 'pro' THEN 'For artists who need a custom domain, deeper catalog capacity, stronger sales presentation, and larger audience tools.'
        WHEN 'collective' THEN 'For galleries, estates, collectives, and organizations managing many works, users, domains, and subscriber relationships.'
        ELSE COALESCE(description, 'ArtsFolio artist portfolio plan.')
    END,
    allowed_artworks = CASE slug WHEN 'free' THEN 25 WHEN 'studio' THEN 250 WHEN 'pro' THEN 1000 WHEN 'collective' THEN 5000 ELSE COALESCE(allowed_artworks, 100) END,
    allowed_email_addresses = CASE slug WHEN 'free' THEN 100 WHEN 'studio' THEN 2500 WHEN 'pro' THEN 10000 WHEN 'collective' THEN 50000 ELSE COALESCE(allowed_email_addresses, 500) END,
    display_order = CASE slug WHEN 'free' THEN 10 WHEN 'studio' THEN 20 WHEN 'pro' THEN 30 WHEN 'collective' THEN 40 ELSE COALESCE(display_order, 100) END
WHERE slug IN ('free', 'studio', 'pro', 'collective');

-- End of file.
