<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php'
);

$required = [
    '$sectionOptions = $this->portfolioSectionOptionsHtml($tenant);',
    'class="artwork-section-assignment"',
    'name="section_ids[]"',
    'SELECT id, name, status',
    'FROM portfolio_sections',
    'replaceArtworkSections($tenant, $artworkId',
    'DELETE FROM artwork_section_assignments',
    'INSERT INTO artwork_section_assignments',
    'WHERE tenant_id = ?',
];

$failures = [];
foreach ($required as $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "ArtworkUploadController missing marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Upload artwork section-assignment check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Artwork upload lists and saves all portfolio-section assignments.\n";

// End of file.
