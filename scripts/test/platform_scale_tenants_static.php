<?php

// Static checks for platform-admin scale tenant controls.

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$files = [
    'app/Platform/ScaleTesting/ScaleTenantFixtureService.php',
    'app/Http/Controllers/Platform/Admin/ScaleTenantsController.php',
    'scripts/dev/seed_scale_dataset.php',
    'public/index.php',
    'app/Http/View/AdminLayout.php',
    'docs/dev/scale-readiness.md',
    'docs/admin/operating-scale-readiness.md',
];

foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing required file: {$file}";
    }
}

$service = readFileOrEmpty($root . '/app/Platform/ScaleTesting/ScaleTenantFixtureService.php');
$controller = readFileOrEmpty($root . '/app/Http/Controllers/Platform/Admin/ScaleTenantsController.php');
$index = readFileOrEmpty($root . '/public/index.php');
$layout = readFileOrEmpty($root . '/app/Http/View/AdminLayout.php');
$script = readFileOrEmpty($root . '/scripts/dev/seed_scale_dataset.php');
$devDocs = readFileOrEmpty($root . '/docs/dev/scale-readiness.md');
$adminDocs = readFileOrEmpty($root . '/docs/admin/operating-scale-readiness.md');

requireContains($service, "public const MARKER_KEY = 'scale_dataset_marker';", 'Scale service must define marker key.');
requireContains($service, "public const MARKER_VALUE = 'artsfolio-scale-fixture-v1';", 'Scale service must define marker value.');
requireContains($service, "public const SLUG_PREFIX = 'scale-';", 'Scale service must define slug prefix.');
requireContains($service, 'JOIN tenant_settings s ON s.tenant_id = t.id', 'Cleanup must require tenant_settings marker join.');
requireContains($service, 't.slug LIKE :slug_prefix', 'Cleanup must require scale slug prefix.');
requireContains($service, 'deleteIfExists(\'tenants\', \'id\')', 'Cleanup must remove marked tenants only through temporary id table.');

requireContains($controller, 'final class ScaleTenantsController', 'Scale tenants controller must exist.');
requireContains($controller, "Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN", 'Scale tenant controls must require platform owner/admin role.');
requireContains($controller, "create scale tenants", 'Create action must require typed confirmation.');
requireContains($controller, "remove scale tenants", 'Remove action must require typed confirmation.');
requireContains($controller, "ScaleTenantFixtureService", 'Controller must use scale fixture service.');

requireContains($index, "ScaleTenantsController as PlatformAdminScaleTenantsController", 'Front controller must import scale tenant controller.');
requireContains($index, "ScaleTenantFixtureService", 'Front controller must import/instantiate scale fixture service.');
requireContains($index, "'/platform/admin/scale-tenants'", 'Front controller must route scale tenant admin page.');
requireContains($index, "'/platform/admin/scale-tenants/create'", 'Front controller must route scale tenant creation.');
requireContains($index, "'/platform/admin/scale-tenants/remove'", 'Front controller must route scale tenant removal.');
requireContains($index, "'/admin/scale-tenants'", 'Legacy admin redirect for scale tenants must exist.');

requireContains($layout, "'scale' => ['/platform/admin/scale-tenants', 'Scale Tenants']", 'Platform admin nav must include Scale Tenants.');
requireContains($script, 'new ScaleTenantFixtureService($pdo, $root)', 'CLI seeder must use the same service as platform admin.');
requireContains($script, 'requireNonProductionSafety', 'CLI seeder must keep production-looking environment guard.');

requireContains($devDocs, '/Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh', 'Developer docs must use correct Downloads apply-script path.');
requireContains($adminDocs, '/Users/bxiie/Downloads/apply_artsfolio_phase0_phase1_scale_admin_update.sh', 'Admin docs must use correct Downloads apply-script path.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Platform scale tenant admin static checks passed.\n";

function readFileOrEmpty(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

function requireContains(string $haystack, string $needle, string $message): void
{
    global $failures;
    if (!str_contains($haystack, $needle)) {
        $failures[] = $message;
    }
}

// End of file.
