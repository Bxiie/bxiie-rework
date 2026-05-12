<?php
/**
 * Add settings defaults for mobile/email/stats/portfolio-tabs/topbar update.
 */
declare(strict_types=1);
$dbPath = getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');
$tenantIds = $db->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN);
$insert = $db->prepare('INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (:tenant_id, :setting_key, :setting_value) ON CONFLICT(tenant_id, setting_key) DO NOTHING');
$defaults = [
    'admin_email' => '',
    'mail_from' => 'noreply@bxiie.com',
    'notify_on_contact' => '1',
    'notify_on_subscriber' => '1',
    'portfolio_sections_as_tabs' => '0',
    'topbar_background_color' => '',
    'topbar_background_image_id' => '',
    'topbar_background_image_mode' => 'cover',
    'about_image_size' => 'medium',
    'contact_image_size' => 'medium',
];
foreach ($tenantIds as $tenantId) {
    foreach ($defaults as $key => $value) {
        $insert->execute(['tenant_id' => (int)$tenantId, 'setting_key' => $key, 'setting_value' => $value]);
    }
}
echo "Mobile/email/stats/tabs/topbar migration complete.\n";
// End of file.
