<?php

declare(strict_types=1);

$file = 'app/Http/Controllers/Platform/Admin/EmailOutboxController.php';
$source = @file_get_contents($file);
$failures = [];

if ($source === false) {
    $failures[] = $file . ' is unreadable.';
} else {
    $needles = [
        '$this->displayUtcTime(',
        '$GLOBALS[\'artsfolio_user_timezone\']',
        'new \DateTimeZone(\'UTC\')',
        '->setTimezone($timezone)',
        'format(\'M j, Y g:i:s A T\')',
    ];

    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $failures[] = $file . ' missing: ' . $needle;
        }
    }

    if (str_contains($source, '$this->escape($statusTimestamp) . \' UTC\'')) {
        $failures[] = $file . ' still appends a hard-coded UTC label.';
    }
}

if ($failures !== []) {
    fwrite(
        STDERR,
        "[FAIL] Email outbox selected-time-zone checks failed:\n - "
        . implode("\n - ", $failures)
        . "\n"
    );
    exit(1);
}

echo "[PASS] Email outbox status timestamps honor the selected time zone.\n";
