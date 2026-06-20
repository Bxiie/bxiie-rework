<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php';
$cssPath = $root . '/public/assets/tenant-admin.css';
$preflightPath = $root . '/scripts/test/preflight.sh';

$controller = file_get_contents($controllerPath);
$css = file_get_contents($cssPath);
$preflight = file_get_contents($preflightPath);
$failures = [];

$checks = [
    [$controller, 'private function settingsSections(): array', 'Settings controller defines settings subpages'],
    [$controller, "'identity' => [", 'Identity settings subpage exists'],
    [$controller, "'typography' => [", 'Typography settings subpage exists'],
    [$controller, "'colors-backgrounds' => [", 'Colors & Backgrounds settings subpage exists'],
    [$controller, "'miscellaneous' => [", 'Miscellaneous settings subpage exists'],
    [$controller, "'custom-css' => [", 'Custom CSS settings subpage exists'],
    [$controller, 'private function settingsSubnav(string $activeSection): string', 'Settings subpage navigation is rendered by helper'],
    [$controller, 'tenant-settings-subnav', 'Settings subpage navigation class is present'],
    [$controller, 'name="settings_section"', 'Settings form posts active section'],
    [$controller, 'settingsKeysForSection($activeSection)', 'Update writes only active subpage keys'],
    [$controller, "'/admin/settings?section=' . rawurlencode(\$activeSection)", 'Save redirects back to active subpage'],
    [$controller, 'match ($activeSection)', 'Edit renders one active settings subpage'],
    [$controller, 'class="plan-edit-form admin-form tenant-settings-form"', 'Settings form has one merged class attribute'],
    [$css, '.tenant-settings-subnav', 'Tenant admin CSS styles settings subpage navigation'],
    [$css, '.tenant-settings-subnav-link.is-active', 'Tenant admin CSS styles active settings subpage'],
    [$preflight, 'tenant_settings_subpages_static.php', 'Preflight runs settings subpages static check'],
];

foreach ($checks as [$haystack, $needle, $label]) {
    if (!str_contains($haystack, $needle)) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}

$identityKeys = [
    'site_title', 'artist_name', 'browser_title', 'copyright_name', 'site_admin_email', 'home_intro',
    'home_tab', 'portfolio_tab', 'about_tab', 'contact_tab', 'portfolio_slug', 'about_slug', 'contact_slug',
];
foreach ($identityKeys as $key) {
    if (!str_contains($controller, "'$key'")) {
        $failures[] = 'Identity subpage key missing: ' . $key;
    }
}

$sectionCount = substr_count($controller, "'label' =>");
if ($sectionCount < 5) {
    $failures[] = 'Expected at least five settings subpage labels, found ' . $sectionCount;
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant settings subpages static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Tenant settings subpages static checks passed.\n";

// End of file.
