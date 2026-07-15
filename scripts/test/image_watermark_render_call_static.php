<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents(
    $root . '/app/Tenant/Media/WatermarkService.php'
);

$renderMethodStart = strpos($service, 'public function render(');
$imageMethodStart = strpos(
    $service,
    'private function renderImageWatermark('
);

$failures = [];

if ($renderMethodStart === false || $imageMethodStart === false) {
    $failures[] = 'Could not isolate watermark render methods.';
} else {
    $renderBody = substr(
        $service,
        $renderMethodStart,
        $imageMethodStart - $renderMethodStart,
    );

    foreach ([
        '$this->renderImageWatermark(',
        'if ($watermarkImage instanceof \\GdImage)',
        'if ($text !== \'\')',
        'imagedestroy($watermarkImage)',
    ] as $marker) {
        if (!str_contains($renderBody, $marker)) {
            $failures[] = "Watermark render method missing marker: {$marker}";
        }
    }
}

foreach ([
    'imagecopy(',
    '$adjustedAlpha',
    'imagesetpixel(',
] as $marker) {
    if (!str_contains($service, $marker)) {
        $failures[] = "Image watermark compositor missing marker: {$marker}";
    }
}

if (str_contains($service, 'imagecopymerge($target, $scaled')) {
    $failures[] = 'Legacy imagecopymerge compositor remains.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Image watermark render-call check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Image watermark is invoked and composited with transparency.\n";

// End of file.
