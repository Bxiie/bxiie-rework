<?php

declare(strict_types=1);

/**
 * Static regression checks for tenant settings save buttons.
 *
 * The settings page used to render as one long form with a save button after
 * every visible section. It now renders one settings subpage at a time, so a
 * single reusable save button is correct as long as the active subpage is
 * preserved and only that subpage's keys are written.
 */

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$css = file_get_contents($root . '/public/assets/tenant-admin.css');

if ($controller === false || $css === false) {
    fwrite(STDERR, "Could not read tenant settings source files.
");
    exit(1);
}

$failures = [];

$checks = [
    [$controller, 'settings-section-actions', 'Reusable save-button action wrapper'],
    [$controller, '<button type="submit">Save site settings</button>', 'Save site settings button'],
    [$controller, 'name="settings_section"', 'Hidden active settings section field'],
    [$controller, '/admin/settings?section=', 'Subpage form action and redirect preserve section'],
    [$controller, 'settingsKeysForSection($activeSection)', 'POST saves only active section keys'],
    [$controller, 'private function settingsSections(): array', 'Settings subpage registry'],
    [$controller, "'identity' => [", 'Identity settings subpage'],
    [$controller, "'typography' => [", 'Typography settings subpage'],
    [$controller, "'colors-backgrounds' => [", 'Colors & Backgrounds settings subpage'],
    [$controller, "'miscellaneous' => [", 'Miscellaneous settings subpage'],
    [$controller, "'custom-css' => [", 'Custom CSS settings subpage'],
    [$css, '.settings-section-actions', 'Tenant admin stylesheet styles section save actions'],
];

foreach ($checks as [$haystack, $needle, $label]) {
    if (!str_contains($haystack, $needle)) {
        $failures[] = $label . ' missing: ' . $needle;
    }
}

$sectionSaveCount = substr_count($controller, '{$saveButton}');
$subpageMode = str_contains($controller, 'settingsKeysForSection($activeSection)')
    && str_contains($controller, 'match ($activeSection)')
    && str_contains($controller, 'settingsSubnav($activeSection)');

if ($subpageMode) {
    if ($sectionSaveCount < 1) {
        $failures[] = sprintf('Expected save button on active settings subpage; found %d placements.', $sectionSaveCount);
    }
} elseif ($sectionSaveCount < 9) {
    $failures[] = sprintf('Expected save button after every settings section; found %d placements.', $sectionSaveCount);
}

if ($failures !== []) {
    fwrite(STDERR, "Tenant settings section save button static check failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Tenant settings section save button static checks passed.
";

// End of file.
