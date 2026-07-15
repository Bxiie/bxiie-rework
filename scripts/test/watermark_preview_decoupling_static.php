<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/MediaController.php'
);

$failures = [];

foreach ([
    'applyPublicWatermark: true',
    'bool $applyPublicWatermark = false',
    '$watermarkEnabled = $applyPublicWatermark',
    '$applyPublicWatermark',
] as $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "MediaController missing marker: {$marker}";
    }
}

if (str_contains(
    $controller,
    '$watermarkEnabled = $requirePublishedArtwork'
)) {
    $failures[] = 'Watermark eligibility is still tied to publication enforcement.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Watermark preview decoupling check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Public watermarking remains enabled during unpublished preview.\n";

// End of file.
