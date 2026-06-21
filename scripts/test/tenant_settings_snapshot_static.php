<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$repositoryPath = $projectRoot . '/app/Tenant/Settings/TenantSettingsRepository.php';
$snapshotPath = $projectRoot . '/app/Tenant/Settings/TenantSettingsSnapshot.php';
$cssControllerPath = $projectRoot . '/app/Http/Controllers/Tenant/TenantCssController.php';

foreach ([$repositoryPath, $snapshotPath, $cssControllerPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required Phase 2 file: {$path}\n");
        exit(1);
    }
}

$repository = (string) file_get_contents($repositoryPath);
$snapshot = (string) file_get_contents($snapshotPath);
$cssController = (string) file_get_contents($cssControllerPath);

$requirements = [
    'repository has request-local snapshot cache' => str_contains($repository, 'private array $snapshots = [];'),
    'get delegates to snapshot' => str_contains($repository, 'return $this->snapshot($tenant)->get($key, $default);'),
    'snapshot bulk loads all settings' => str_contains($repository, 'SELECT setting_key, setting_value'),
    'snapshot query is tenant scoped' => str_contains($repository, 'WHERE tenant_id = :tenant_id'),
    'set keeps loaded snapshot coherent' => str_contains($repository, '->with($key, $value)'),
    'snapshot exposes default-aware get' => str_contains($snapshot, 'public function get(string $key, ?string $default = null): ?string'),
    'snapshot uses array_key_exists for null values' => str_contains($snapshot, 'array_key_exists($key, $this->values)'),
    'CSS controller explicitly uses snapshot' => str_contains($cssController, '$this->settings->snapshot($tenant)'),
];

foreach ($requirements as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Tenant settings snapshot static check failed: {$label}.\n");
        exit(1);
    }
}

$getStart = strpos($repository, 'public function get(');
$setStart = strpos($repository, 'public function set(');
if ($getStart === false || $setStart === false || $setStart <= $getStart) {
    fwrite(STDERR, "Could not isolate TenantSettingsRepository::get().\n");
    exit(1);
}

$getMethod = substr($repository, $getStart, $setStart - $getStart);
if (str_contains($getMethod, 'prepare(') || str_contains($getMethod, 'SELECT ')) {
    fwrite(STDERR, "TenantSettingsRepository::get() must not issue one query per key.\n");
    exit(1);
}

echo "Tenant settings snapshot static checks passed.\n";

// End of file.
