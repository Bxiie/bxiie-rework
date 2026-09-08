<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Tenant/Social/SocialPermissionService.php');

$checks = [
    'owner/admin authorization must be checked before active-membership gating' =>
        strpos($service, "hasTenantRole($tenant->tenantId, $userId, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])")
            < strpos($service, '!$this->activeTenantMembership($tenant->tenantId, $userId)'),
    'editors still require an active tenant membership' =>
        str_contains($service, "if (!$this->activeTenantMembership($tenant->tenantId, $userId))"),
    'editors still require the editor role' =>
        str_contains($service, "if (!$this->hasTenantRole($tenant->tenantId, $userId, ['editor']))"),
    'editors still require explicit social.publish permission' =>
        str_contains($service, 'return $this->hasExplicitPermission($tenant->tenantId, $userId, self::PUBLISH_PERMISSION);'),
];

$failures = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Social permission canonical-admin regression failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Social permission canonical-admin regression passed.\n";
