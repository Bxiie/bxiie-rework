<?php

declare(strict_types=1);

// Regression coverage: deleting an email signup used to be a plain <form>
// POST that redirected and reloaded the whole /admin/email-signups list,
// forcing the operator to rescroll after every single delete. The delete
// button is now a fetch()-driven action that removes just its own <tr>, and
// EmailSignupsController::delete() returns JSON instead of a redirect when
// called that way.

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/EmailSignupsController.php') ?: '';
$failures = [];

foreach ([
    'data-role="email-signup-delete"',
    'data-signup-id="{$id}"',
    'id="email-signup-row-{$id}"',
    "fetch('/admin/email-signups/delete'",
    "'X-Requested-With': 'XMLHttpRequest'",
] as $needle) {
    if (!str_contains($controller, $needle)) {
        $failures[] = "EmailSignupsController index() missing {$needle}";
    }
}

// The old reload-based delete <form> must actually be gone, not just
// supplemented, or there would be two conflicting delete controls per row.
if (preg_match('/<form[^>]*action="\/admin\/email-signups\/delete"/', $controller) === 1) {
    $failures[] = 'A plain reloading <form> still posts to /admin/email-signups/delete.';
}

if (!preg_match('/function delete\(.*?\n    \}\n/s', $controller, $deleteMatch)) {
    $failures[] = 'Could not isolate EmailSignupsController::delete().';
}
$delete = $deleteMatch[0] ?? '';

foreach ([
    "HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest'",
    "Response::json(['ok' => true])",
] as $needle) {
    if (!str_contains($delete, $needle)) {
        $failures[] = "EmailSignupsController::delete() missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Email signup AJAX delete static checks passed.\n";

// End of file.
