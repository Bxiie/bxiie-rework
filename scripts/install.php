<?php
/**
 * One-time installer: creates schema and default Bxiie tenant/admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$db = $container['db'];
$db->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));

$db->exec("INSERT OR IGNORE INTO tenants (slug, display_name, status, created_at) VALUES ('bxiie', 'Bxiie', 'active', datetime('now'))");
$tenantId = (int) $db->query("SELECT id FROM tenants WHERE slug = 'bxiie'")->fetchColumn();

$stmt = $db->prepare('INSERT OR IGNORE INTO tenant_domains (tenant_id, domain) VALUES (:tenant_id, :domain)');
$stmt->execute(['tenant_id' => $tenantId, 'domain' => $container['config']['default_domain']]);

$settings = [
    'site_title' => 'Bxiie',
    'home_tab' => 'Home',
    'portfolio_tab' => 'Portfolio',
    'about_tab' => 'About',
    'contact_tab' => 'Contact',
    'primary_color' => '#111111',
    'accent_color' => '#c9a85f',
    'background_color' => '#f7f2e8',
    'about_content' => 'Artist statement and biography go here.',
    'contact_details' => 'Contact details go here.',
    'copyright_year' => date('Y'),
];
foreach ($settings as $key => $value) {
    $stmt = $db->prepare('INSERT OR IGNORE INTO settings (tenant_id, setting_key, setting_value) VALUES (:tenant_id, :key, :value)');
    $stmt->execute(['tenant_id' => $tenantId, 'key' => $key, 'value' => $value]);
}

foreach (['Underground','Opposition','Unrealized','Isolation','SeriesSix','Burn','PointsOfContention','Swarm'] as $i => $name) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
    $stmt = $db->prepare('INSERT OR IGNORE INTO portfolio_sections (tenant_id, name, slug, sort_order) VALUES (:tenant_id, :name, :slug, :sort_order)');
    $stmt->execute(['tenant_id' => $tenantId, 'name' => $name, 'slug' => $slug, 'sort_order' => ($i + 1) * 10]);
}

$email = getenv('ADMIN_EMAIL') ?: 'admin@bxiie.com';
$password = getenv('ADMIN_PASSWORD') ?: 'ChangeMeNow-CreateRealPassword';
$stmt = $db->prepare('INSERT OR IGNORE INTO users (tenant_id, name, email, role, password_hash, created_at) VALUES (:tenant_id, :name, :email, :role, :password_hash, datetime("now"))');
$stmt->execute(['tenant_id' => $tenantId, 'name' => 'Site Owner', 'email' => $email, 'role' => 'owner', 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);

echo "Installed. Login at /admin/login with {$email}. Change the password immediately.\n";

// End of file.
