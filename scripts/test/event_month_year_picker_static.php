<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/EventsController.php'
);
$publicController = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/HomeController.php'
);
$publicCss = (string) file_get_contents($root . '/public/assets/site.css');

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

foreach ([
    'eventDisplayDate',
    "format('F Y')",
    'class=\"event-notes\"',
] as $marker) {
    if (!str_contains($publicController, $marker)) {
        $failures[] = "Public event rendering missing marker: {$marker}";
    }
}

foreach (['.event-notes', 'font: inherit', 'font-size: inherit', 'line-height: inherit'] as $marker) {
    if (!str_contains($publicCss, $marker)) {
        $failures[] = "Public event notes typography missing marker: {$marker}";
    }
}

if (str_contains($publicController, '<div class=\"prose small\">{$notes}</div>')) {
    $failures[] = 'Public event notes still use the mismatched prose-small typography.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Event month/year picker check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Event definitions and public display use month/year with consistent notes typography.\n";

// End of file.
