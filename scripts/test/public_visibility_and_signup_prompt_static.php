<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$sources = [
    'settings' => (string) file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SettingsController.php'),
    'getting_started' => (string) file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/GettingStartedController.php'),
    'home' => (string) file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php'),
    'signup' => (string) file_get_contents($root . '/app/Http/Controllers/Tenant/SignupController.php'),
];
$required = [
    'settings' => ['suppress_mailing_list_dialog', 'suppress_contact_page', 'suppress_about_page', 'hide_portfolio_all_button', 'Public page visibility'],
    'getting_started' => ['Tenant Admin URL:', '$tenantAdminUrl'],
    'home' => ["settingEnabled(\$tenant, 'suppress_about_page')", "settingEnabled(\$tenant, 'suppress_contact_page')", "settingEnabled(\$tenant, 'hide_portfolio_all_button')", 'private function mailingListDialog', '60000', 'artsfolio_known_visitor', 'delayed_dialog'],
    'signup' => ['bool $knownVisitor = false', 'artsfolio_known_visitor=1', "'signup_sent=1', true"],
];
foreach ($required as $name => $markers) {
    foreach ($markers as $marker) {
        if (!str_contains($sources[$name], $marker)) {
            $failures[] = "{$name} missing marker: {$marker}";
        }
    }
}
if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Public visibility and signup prompt check failed:\n");
    foreach ($failures as $failure) fwrite(STDERR, "[FAIL]  - {$failure}\n");
    exit(1);
}
echo "[PASS] Public visibility and delayed signup prompt check passed.\n";

// End of file.
