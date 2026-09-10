-- Studio uses an ArtsFolio subdomain. Custom domains begin with Professional.
UPDATE plans
SET custom_domain_included = FALSE
WHERE slug = 'studio';

-- End of file.
