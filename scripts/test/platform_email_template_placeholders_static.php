#!/usr/bin/php
<?php

/** Regression check for the platform email-template placeholder reference. */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Http/Controllers/Platform/Admin/EmailTemplatesController.php';
if (!is_file($path)) {
    fwrite(STDERR, "[FAIL] Missing email-template controller.\n");
    exit(1);
}

$body = (string) file_get_contents($path);
$required = [
    'Available placeholders',
    'placeholderReferenceHtml',
    "'tenant_name' =>",
    "'reset_url' =>",
    "'verification_url' =>",
    "'cart_url' =>",
    "'billing_url' =>",
    "'invoice_number' =>",
    "'support_email' =>",
    "'report_lines' =>",
    '<th>Available in</th>',
    '<th>Meaning</th>',
];

foreach ($required as $marker) {
    if (!str_contains($body, $marker)) {
        fwrite(STDERR, "[FAIL] Missing placeholder-reference marker: {$marker}\n");
        exit(1);
    }
}

echo "[PASS] Platform email-template placeholder reference static check passed.\n";

// End of file.
