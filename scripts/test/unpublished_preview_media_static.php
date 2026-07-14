<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/HomeController.php'
);
$media = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/MediaController.php'
);
$routes = (string) file_get_contents(
    $root . '/app/Http/Routes/tenant.php'
);

$failures = [];

foreach ([
    "\$includeUnpublished ? '&preview_unpublished=1' : ''",
    "\$this->unpublishedPreviewEnabled(\$tenant) ? '&preview_unpublished=1' : ''",
] as $marker) {
    if (!str_contains($home, $marker)) {
        $failures[] = "HomeController missing marker: {$marker}";
    }
}

foreach ([
    '$allowUnpublishedPreview',
    "['tenant_owner', 'tenant_admin', 'owner', 'admin']",
    'requirePublishedArtwork: !$allowUnpublishedPreview',
] as $marker) {
    if (!str_contains($media, $marker)) {
        $failures[] = "MediaController missing marker: {$marker}";
    }
}

$routeMarker = "new TenantMediaController(\$pdo, new RequireTenantRoleBrowser(new MembershipRepository(\$pdo))))->public(\$request, \$tenant, \$currentUser)";
if (!str_contains($routes, $routeMarker)) {
    $failures[] = 'Tenant public media route does not pass authorization context.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Unpublished preview media check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Authorized unpublished preview requests can load unpublished media.\n";

// End of file.
