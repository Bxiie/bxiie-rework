sqlite3 ../bxiie-cms/database/bxiie.sqlite<<'SQL'
.tables
SELECT setting_key, setting_value FROM settings
WHERE setting_key IN (
  'about_content',
  'contact_details',
  'facebook_url',
  'instagram_url',
  'linkedin_url',
  'about_image_id',
  'contact_image_id',
  'exhibitions_heading',
  'exhibitions_display_mode'
);

SELECT *
FROM exhibitions
ORDER BY event_date DESC
LIMIT 20;

SQL
