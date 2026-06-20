<?php

declare(strict_types=1);

/**
 * Static regression checks for repeated tenant settings save buttons.
 */

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php');
$css = file_get_contents($root . '/public/assets/tenant-admin.css');

if ($controller === false || $css === false) {
    fwrite(STDERR, "Could not read tenant settings source files.
");
    exit(1);
}

if (!str_contains($controller, 'settings-section-actions')
    || !str_contains($controller, '<button type="submit">Save site settings</button>')) {
    fwrite(STDERR, "Tenant settings form does not define the reusable section save button.
");
    exit(1);
}

$sectionSaveCount = substr_count($controller, '{$saveButton}');
if ($sectionSaveCount < 9) {
    fwrite(STDERR, sprintf("Expected save button after every settings section; found %d placements.
", $sectionSaveCount));
    exit(1);
}

if (!str_contains($controller, '<legend>Identity</legend>')
    || !str_contains($controller, '<legend>Colors and background</legend>')
    || !str_contains($controller, '<legend>Tenant CSS</legend>')) {
    fwrite(STDERR, "Tenant settings sections expected by the save-button regression are missing.
");
    exit(1);
}

if (!str_contains($css, '.settings-section-actions')) {
    fwrite(STDERR, "Tenant admin stylesheet does not style section save actions.
");
    exit(1);
}

echo "Tenant settings section save button static checks passed.
";

// End of file.
