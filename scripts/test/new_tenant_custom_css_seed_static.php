<?php

declare(strict_types=1);

/**
 * Regression checks for documented Custom CSS on newly created tenants.
 */

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Platform/Signup/TenantSignupService.php'
);
$editor = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php'
);
$publicCss = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/TenantCssController.php'
);
$migration = (string) file_get_contents(
    $root . '/database/migrations/0067_repair_new_tenant_css_setting_key.sql'
);

$failures = [];

$requiredService = [
    "setting_key = 'tenant_css'",
    "'setting_key' => 'tenant_css'",
    "\$path = \$root . '/public/assets/site.css';",
    'ArtsFolio Custom CSS',
    'HOW THE CASCADE WORKS',
    'SAFE WORKFLOW',
    'IMPORTANT CSS VARIABLES',
    'COMMON SELECTORS',
    'RESPONSIVE RULES',
    'Tenant additions',
];

foreach ($requiredService as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "TenantSignupService missing marker: {$marker}";
    }
}

if (!str_contains($editor, "\$this->setting(\$tenant, 'tenant_css', '')")) {
    $failures[] = 'Tenant Admin editor does not read tenant_css.';
}

if (!str_contains($publicCss, "\$settings->get('tenant_css', '')")) {
    $failures[] = 'Public TenantCssController does not read tenant_css.';
}

$requiredMigration = [
    "source.setting_key = 'custom_css'",
    "'tenant_css'",
    'NULLIF(TRIM(COALESCE(target.setting_value',
    'DELETE orphan',
];

foreach ($requiredMigration as $marker) {
    if (!str_contains($migration, $marker)) {
        $failures[] = "Migration missing marker: {$marker}";
    }
}

if (str_contains($service, "setting_key = 'custom_css'")) {
    $failures[] = 'TenantSignupService still looks up the unused custom_css key.';
}

if (str_contains($service, "'setting_key' => 'custom_css'")) {
    $failures[] = 'TenantSignupService still inserts the unused custom_css key.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] New tenant Custom CSS seed check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] New tenants receive documented CSS through tenant_css.\n";

// End of file.
