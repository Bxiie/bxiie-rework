<?php

declare(strict_types=1);

/**
 * Static regression checks for platform contact persistence and workflow routes.
 */

$root = dirname(__DIR__, 2);
$checks = [
    'database/migrations/0034_platform_contact_messages.sql' => [
        'MODIFY tenant_id BIGINT UNSIGNED NULL',
        'idx_contact_messages_platform_status',
    ],
    'app/Platform/Contact/PlatformContactMessageRepository.php' => [
        'tenant_id,',
        'NULL,',
        'UPDATE contact_messages',
        'WHERE tenant_id IS NULL',
    ],
    'app/Http/Controllers/Platform/MarketingController.php' => [
        'recordPlatformContact',
        'PlatformContactMessageRepository',
        'Manage it: /platform/admin/contacts',
        'platform.contact_notification',
    ],
    'app/Http/Controllers/Platform/Admin/ContactMessagesController.php' => [
        'Platform Contacts',
        'tenant_id IS NULL',
        '/platform/admin/contacts/status',
        '/platform/admin/contacts/delete',
        '/platform/admin/contacts.csv',
    ],
    'app/Http/View/AdminLayout.php' => [
        "'contacts' => ['/platform/admin/contacts', 'Contacts']",
    ],
    'app/Http/Routes/platform.php' => [
        '/platform/admin/contacts',
        '/platform/admin/contacts.csv',
        '/platform/admin/contact-messages',
        "'Location' => '/platform/admin/contacts'",
    ],
    'scripts/test/preflight.sh' => [
        'scripts/test/platform_contact_management_static.php',
    ],
];

$errors = [];
foreach ($checks as $relativePath => $needles) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        $errors[] = "Missing file: {$relativePath}";
        continue;
    }

    $contents = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = "Missing expected text in {$relativePath}: {$needle}";
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "Platform contact management static checks passed.\n";

// End of file.
