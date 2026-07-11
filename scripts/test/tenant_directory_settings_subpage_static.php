<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$settingsController = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$discoveryController = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/DiscoverySettingsController.php');
$tenantNav = file_get_contents($root . '/app/Http/View/TenantAdminNav.php');
$preflight = file_get_contents($root . '/scripts/test/preflight.sh');

$failures = [];

$checks = [
    [$settingsController, "'directory' => [", 'Directory settings subpage section'],
    [$settingsController, "'label' => 'Directory'", 'Directory settings subpage label'],
    [$settingsController, "'directory' => \$directoryContent", 'Directory settings subpage content switch'],
    [$settingsController, 'platform_directory_opt_in', 'Directory opt-in setting key'],
    [$settingsController, 'platform_directory_thumbnail_artwork_id', 'Directory thumbnail setting key'],
    [$settingsController, 'platform_directory_summary', 'Directory summary setting key'],
    [$settingsController, 'directorySettingsContent', 'Directory settings renderer'],
    [$settingsController, 'validDirectoryArtworkId', 'Directory thumbnail validation'],
    [$settingsController, "in_array(\$key, ['platform_directory_opt_in', 'watermark_enabled'], true)", 'Directory checkbox save handling'],
    [$settingsController, "isset(\$_POST[\$key]) ? '1' : '0'", 'Directory checkbox unchecked-state normalization'],
    [$discoveryController, "Location' => '/admin/settings?section=directory'", 'Legacy directory GET redirect'],
    [$discoveryController, "Location' => '/admin/settings?section=directory&notice=saved'", 'Legacy directory POST redirect'],
    [$preflight, 'tenant_directory_settings_subpage_static.php', 'Preflight wiring'],
];

foreach ($checks as [$haystack, $needle, $label]) {
    if (!str_contains($haystack, $needle)) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}

if (str_contains($tenantNav, "'directory' => ['/admin/directory', 'Directory']")) {
    $failures[] = 'Standalone tenant Directory nav item should be removed now that Directory is a settings subpage.';
}

if (!str_contains($settingsController, '/admin/settings?section=')) {
    $failures[] = 'Settings subnav URL pattern missing.';
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant directory settings subpage static check failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Tenant directory settings subpage static checks passed.
";

$navPath = $root . '/app/Http/View/TenantAdminNav.php';
$settingsPath = $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php';

if (!is_file($navPath) || !is_file($settingsPath)) {
    fwrite(STDERR, "[FAIL] Missing tenant Directory navigation/settings source files.\n");
    exit(1);
}

$navSource = (string) file_get_contents($navPath);
$settingsSource = (string) file_get_contents($settingsPath);

if (str_contains($navSource, "'directory' => ['/admin/directory', 'Directory']")) {
    fwrite(
        STDERR,
        "[FAIL] Standalone tenant Directory nav item should be removed now that Directory is a settings subpage.\n"
    );
    exit(1);
}

if (
    !str_contains($settingsSource, 'Directory')
    && !str_contains($settingsSource, 'directory')
) {
    fwrite(
        STDERR,
        "[FAIL] Tenant Settings no longer exposes the Directory subpage.\n"
    );
    exit(1);
}

// End of file.
