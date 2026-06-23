<?php

declare(strict_types=1);

/**
 * Ensures mutating test scripts include the production safety guard.
 */

$root = dirname(__DIR__, 2);

$scripts = [
    'scripts/test/tenant_role_access.php',
    'scripts/test/tenant_admin_role.php',
    'scripts/test/platform_admin_role.php',
    'scripts/test/password_auth.php',
    'scripts/test/email_signup_consent.php',
    'scripts/test/tenant_audit_log_list.php',
    'scripts/test/contact_message_status.php',
    'scripts/test/identity_membership.php',
    'scripts/test/tenant_admin_lists.php',
    'scripts/test/tenant_settings_admin.php',
    'scripts/test/tenant_settings.php',
    'scripts/test/tenant_notifications.php',
    'scripts/test/contact_signup_records.php',
];

foreach ($scripts as $script) {
    $path = $root . '/' . $script;

    if (!is_file($path)) {
        continue;
    }

    $contents = (string) file_get_contents($path);

    if (!str_contains($contents, "require_once __DIR__ . '/TestEnvironment.php';")) {
        fwrite(STDERR, "{$script} missing TestEnvironment include.\n");
        exit(1);
    }

    if (!str_contains($contents, 'TestEnvironment::skipIfProduction')) {
        fwrite(STDERR, "{$script} missing production skip guard.\n");
        exit(1);
    }
}

echo "Production mutation guard smoke test passed.\n";

// End of file.
