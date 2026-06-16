<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Platform/MarketingController.php');
$preflight = file_get_contents($root . '/scripts/test/preflight.sh');

$required = [
    'use App\\Platform\\Email\\EmailOutboxRepository;',
    'platform.contact_notification',
    'queuePlatformContactNotification',
    'ARTSFOLIO_PLATFORM_CONTACT_EMAIL',
    'info@artsfol.io',
    'new EmailOutboxRepository($this->pdo)',
];

foreach ($required as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "MarketingController is missing required platform contact email behavior: {$needle}\n");
        exit(1);
    }
}

if (preg_match('/templateKey:\s*["\']platform\.contact_notification["\']/', $controller) !== 1) {
    fwrite(STDERR, "Platform contact email queue call does not use the expected template key.\n");
    exit(1);
}

if (str_contains($controller, 'example.test')) {
    fwrite(STDERR, "Platform contact email path must not use .example.test recipients.\n");
    exit(1);
}

if (!str_contains($preflight, 'scripts/test/platform_contact_email_static.php')) {
    fwrite(STDERR, "preflight.sh must run platform_contact_email_static.php.\n");
    exit(1);
}

echo "Platform contact email notification static test passed.\n";

// End of file.
