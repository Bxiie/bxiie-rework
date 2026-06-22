<?php

// Static checks for platform-admin scale tenant controls.

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$files = [
    'app/Platform/ScaleTesting/ScaleTenantFixtureService.php',
    'app/Http/Controllers/Platform/Admin/ScaleTenantsController.php',
    'app/Platform/Jobs/Handlers/ScaleTenantFixtureJobHandler.php',
    'scripts/dev/seed_scale_dataset.php',
    'app/Http/Routes/platform.php',
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
$platformRoutes = readFileOrEmpty($root . '/app/Http/Routes/platform.php');
$layout = readFileOrEmpty($root . '/app/Http/View/AdminLayout.php');
$script = readFileOrEmpty($root . '/scripts/dev/seed_scale_dataset.php');
$worker = readFileOrEmpty($root . '/scripts/workers/run_once.php');
$handler = readFileOrEmpty($root . '/app/Platform/Jobs/Handlers/ScaleTenantFixtureJobHandler.php');
$devDocs = readFileOrEmpty($root . '/docs/dev/scale-readiness.md');
$adminDocs = readFileOrEmpty($root . '/docs/admin/operating-scale-readiness.md');

requireContains($service, "public const MARKER_KEY = 'scale_dataset_marker';", 'Scale service must define marker key.');
requireContains($service, "public const MARKER_VALUE = 'artsfolio-scale-fixture-v1';", 'Scale service must define marker value.');
requireContains($service, "public const SLUG_PREFIX = 'scale-';", 'Scale service must define slug prefix.');
requireContains($service, "public const USER_EMAIL_DOMAIN = 'scale-fixtures.artsfol.io';", 'Scale service must define isolated scale user email domain.');
requireContains($service, "private const PLAN_SEQUENCE = ['free', 'studio', 'pro', 'collective'];", 'Scale service must create a varied plan mix.');
requireContains($service, 'ensureTenantUsers($tenantId', 'Scale tenants must create tenant users.');
requireContains($service, 'upsertTenantPlanAssignment($tenantId', 'Scale tenants must receive plan assignments.');
requireContains($service, 'allowed_admin_users', 'Scale users must match plan admin-user limits when available.');
requireContains($service, 'scale_fixture_user_ids', 'Cleanup must isolate scale fixture users before deleting them.');
requireContains($service, 'deleteScaleUsers()', 'Cleanup must remove scale fixture users.');
requireContains($service, 'JOIN tenant_settings s ON s.tenant_id = t.id', 'Cleanup must require tenant_settings marker join.');
requireContains($service, 't.slug LIKE :slug_prefix', 'Cleanup must require scale slug prefix.');
requireContains($service, 'deleteIfExists(\'tenants\', \'id\')', 'Cleanup must remove marked tenants only through temporary id table.');

requireContains($controller, 'final class ScaleTenantsController', 'Scale tenants controller must exist.');
requireContains($controller, "Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN", 'Scale tenant controls must require platform owner/admin role.');
requireContains($controller, "create scale tenants", 'Create action must require typed confirmation.');
requireContains($controller, "remove scale tenants", 'Remove action must require typed confirmation.');
requireContains($controller, "ScaleTenantFixtureService", 'Controller must use scale fixture service.');
requireContains($controller, "BackgroundJobRepository", 'Controller must use background jobs for long scale operations.');
requireContains($controller, "enqueue('scale_tenants.seed'", 'Create/reset must enqueue scale tenant seed jobs instead of running in the browser.');
requireContains($controller, "enqueue('scale_tenants.cleanup'", 'Remove must enqueue scale tenant cleanup jobs instead of running in the browser.');
requireContains($controller, 'name="tenants" value="1000" min="1"', 'Platform admin must default to 1000 tenants without an artificial max attribute.');
requireNotContains($controller, "\$this->boundedInt(\$_POST['tenants'] ?? 1000, 1, 5000", 'Platform admin must not cap the number of scale tenants at 5000.');
requireNotContains($service, "min(5000, \$tenantCount)", 'Scale service must not cap the number of scale tenants at 5000.');
requireNotContains($controller, '$this->fixtures->seed($tenantCount', 'Controller must not run large seed work directly in the browser request.');

requireContains($platformRoutes, "ScaleTenantsController as PlatformAdminScaleTenantsController", 'Platform routes must import scale tenant controller.');
requireContains($platformRoutes, "ScaleTenantFixtureService", 'Platform routes must import/instantiate scale fixture service.');
requireContains($platformRoutes, "BackgroundJobRepository", 'Platform routes must pass background job repository to scale tenant controller.');
requireContains($platformRoutes, "'/platform/admin/scale-tenants'", 'Platform routes must register scale tenant admin page.');
requireContains($platformRoutes, "'/platform/admin/scale-tenants/create'", 'Platform routes must register scale tenant creation.');
requireContains($platformRoutes, "'/platform/admin/scale-tenants/remove'", 'Platform routes must register scale tenant removal.');
requireContains($platformRoutes, "'/admin/scale-tenants'", 'Legacy admin redirect for scale tenants must exist.');

requireContains($layout, "'scale' => ['/platform/admin/scale-tenants', 'Scale Tenants']", 'Platform admin nav must include Scale Tenants.');
requireContains($script, 'new ScaleTenantFixtureService($pdo, $root)', 'CLI seeder must use the same service as platform admin.');
requireContains($script, 'requireNonProductionSafety', 'CLI seeder must keep production-looking environment guard.');
requireContains($script, "positiveInt((string) (\$options['tenants'] ?? '1000'), 1000)", 'CLI seeder must default to 1000 tenants without an artificial upper cap.');
requireContains($handler, 'final class ScaleTenantFixtureJobHandler', 'Scale tenant background job handler must exist.');
requireContains($handler, '$this->fixtures->seed($tenantCount', 'Scale tenant background job handler must perform seed work.');
requireContains($handler, "positiveInt(\$payload['tenants'] ?? 1000, 1000)", 'Scale tenant job handler must default to 1000 tenants without an artificial upper cap.');
requireContains($handler, '$this->fixtures->cleanup()', 'Scale tenant background job handler must perform cleanup work.');
requireContains($worker, "case 'scale_tenants.seed':", 'Worker must route scale tenant seed jobs.');
requireContains($worker, "case 'scale_tenants.cleanup':", 'Worker must route scale tenant cleanup jobs.');

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

function requireNotContains(string $haystack, string $needle, string $message): void
{
    global $failures;
    if (str_contains($haystack, $needle)) {
        $failures[] = $message;
    }
}

// End of file.
