<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Tenant/Media/WatermarkService.php'
);

$failures = [];

foreach ([
    'private function trimTransparentCanvas',
    '$trimmed = $this->trimTransparentCanvas($watermark)',
    'imagecopyresampled($scaled, $trimmed',
    '$alpha < 120',
    '$cropWidth',
    '$cropHeight',
] as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "WatermarkService missing marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Transparent watermark canvas trim check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Transparent watermark canvases are trimmed before scaling.\n";

// End of file.
