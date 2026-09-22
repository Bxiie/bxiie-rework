<?php

declare(strict_types=1);
require dirname(__DIR__, 3) . '/vendor/autoload.php';

// Isolated layout fixture: no live data is read or changed.
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE tenant_settings (tenant_id INTEGER, setting_key TEXT, setting_value TEXT)');
$body = '<div class="dashboard-grid">' . str_repeat('<article class="admin-card"><h3>Artwork</h3><p>Grid preview</p></article>', 12) . '</div>';
if (($argv[1] ?? '') === 'platform') {
    echo App\Http\View\AdminLayout::renderShell('Artworks', $body);
} else {
    $tenantId = (int) ($argv[1] ?? 1);
    $tenant = new App\Platform\Tenancy\TenantContext($tenantId, 'fixture', 'fixture', 'Artist studio', 'artsfolio.test', 'custom', true);
    echo (new App\Http\View\TenantAdminLayout(new App\Tenant\Settings\TenantSettingsRepository($pdo)))->render($tenant, 'Artworks', $body, 'artworks');
}
