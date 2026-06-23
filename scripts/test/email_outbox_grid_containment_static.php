<?php

declare(strict_types=1);

/**
 * Regression checks for Email Outbox grid containment.
 */

$root = dirname(__DIR__, 2);
$controller = file_get_contents(
    $root
    . '/app/Http/Controllers/Platform/Admin/'
    . 'EmailOutboxController.php'
);
$css = file_get_contents(
    $root . '/public/assets/admin-shell-refactor.css'
);
$layout = file_get_contents(
    $root . '/app/Http/View/AdminLayout.php'
);

if ($controller === false || $css === false || $layout === false) {
    fwrite(STDERR, "[FAIL] Could not read Email Outbox grid files.\n");
    exit(1);
}

$controllerNeedles = [
    'class="admin-table-wrap email-outbox-table-wrap"',
    'class="admin-table"',
];

$cssNeedles = [
    '.email-outbox-table-wrap {',
    'overflow-x: auto;',
    '.email-outbox-table-wrap .admin-error-details pre {',
    'white-space: pre-wrap;',
    'overflow-wrap: anywhere;',
];

$layoutNeedles = [
    'admin-shell-refactor.css?v=20260623-email-outbox-containment',
];

foreach ($controllerNeedles as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] EmailOutboxController.php missing: {$needle}\n"
        );
        exit(1);
    }
}

foreach ($cssNeedles as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] admin-shell-refactor.css missing: {$needle}\n"
        );
        exit(1);
    }
}

foreach ($layoutNeedles as $needle) {
    if (!str_contains($layout, $needle)) {
        fwrite(
            STDERR,
            "[FAIL] AdminLayout.php missing: {$needle}\n"
        );
        exit(1);
    }
}

echo "[PASS] Email Outbox grid containment checks passed.\n";

// End of file.
