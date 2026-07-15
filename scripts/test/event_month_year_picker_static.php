<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/EventsController.php'
);

$failures = [];

foreach ([
    'type="month" name="exhibition_date"',
    'Month and year',
    '$storedEventDate = trim',
    '$eventMonth = preg_match',
    'substr($storedEventDate, 0, 7)',
    "preg_match('/^\\d{4}-\\d{2}$/', \$eventMonth)",
    'Enter a valid event month and year',
    "\$eventMonth . '-01'",
    "'exhibition_date' => \$exhibitionDate",
] as $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "EventsController missing marker: {$marker}";
    }
}

if (
    str_contains(
        $controller,
        '<input name="exhibition_date"'
    )
    || str_contains(
        $controller,
        'type="date" name="exhibition_date"'
    )
) {
    $failures[] = 'Day-level or plain event date input remains.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Event month/year picker check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Event definitions accept month and year without requiring a day.\n";

// End of file.
