<?php

declare(strict_types=1);

// Verifies that tenant help articles contain practical, in-depth user guidance
// for each slugged help page rather than placeholder descriptions.

$path = __DIR__ . '/../../app/Http/Controllers/Platform/HelpController.php';
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "[FAIL] Could not read HelpController.php\n");
    exit(1);
}

$required = [
    'getting-started' => ['Recommended first build', 'Open your admin', 'Test visitor paths'],
    'new-admin-tour' => ['Sign in and open the dashboard', 'Configure identity and branding', 'Invite helpers, verify domain, and launch'],
    'tenant-admin-functions' => ['Identity, branding, and public content', 'Artwork and portfolio', 'Access, domains, billing, discovery, and diagnostics'],
    'branding' => ['How to update branding', 'How to update About and Contact', 'Common mistakes'],
    'artworks' => ['Upload a new artwork', 'Publication status', 'Artwork QA checklist'],
    'events' => ['Create an event', 'What belongs in events', 'Event QA checklist'],
    'sales' => ['Before enabling sales on artwork', 'Refund guardrails', 'Failed refund messages stop further refund attempts until investigated'],
    'messages-email' => ['Contact messages', 'Email signups', 'Testing the public forms'],
    'users-domains-billing' => ['Use the minimum useful role', 'Follow DNS instructions exactly', 'Review plan state'],
    'directory' => ['Before opting in', 'Configure directory listing', 'Troubleshooting directory visibility'],
    'stats' => ['Open and read stats', 'How to use stats for decisions', 'Why stats may look wrong'],
    'audit' => ['Audit Log', 'Routes', 'Route troubleshooting workflow'],
    'training-videos' => ['Video directory', 'How to use these videos when they are recorded', 'Video link pending'],
];

$failures = [];
foreach ($required as $slug => $markers) {
    if (!preg_match("/'" . preg_quote($slug, '/') . "'\\s*=>\\s*\\[/", $source)) {
        $failures[] = "Missing article slug: {$slug}";
        continue;
    }
    foreach ($markers as $marker) {
        if (!str_contains($source, $marker)) {
            $failures[] = "Missing marker for {$slug}: {$marker}";
        }
    }
}

foreach (['Click <strong>Upload Artwork</strong> in the sidebar', 'Click <strong>Routes</strong> in the sidebar', '// End of file.'] as $marker) {
    if (!str_contains($source, $marker)) {
        $failures[] = "Missing required marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant help article content static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Tenant help article content static check passed.\n";

// End of file.
