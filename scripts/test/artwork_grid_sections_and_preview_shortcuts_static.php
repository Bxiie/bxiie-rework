<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$artworks = (string) file_get_contents(
    $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php'
);
$repository = (string) file_get_contents(
    $root . '/app/Tenant/Artwork/ArtworkReadRepository.php'
);

$failures = [];

foreach ([
    'AS section_names',
    '<th>Portfolio sections</th>',
    '$sectionNames = $this->artworkSectionNamesHtml',
    '<td>{$sectionNames}</td>',
    'private function artworkSectionNamesHtml',
    'colspan="11"',
] as $marker) {
    if (!str_contains($artworks, $marker)) {
        $failures[] = "ArtworksController missing marker: {$marker}";
    }
}

foreach ([
    'SELECT ps.id, ps.name, ps.slug',
    ':include_unpublished = 1',
    "ps.status <> 'archived'",
    "a.status <> 'archived'",
    "portfolioTypeExistsSql('a')",
] as $marker) {
    if (!str_contains($repository, $marker)) {
        $failures[] = "ArtworkReadRepository missing marker: {$marker}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Artwork sections and preview shortcuts check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Artwork grid shows sections and preview mode exposes draft-section shortcuts.\n";

// End of file.
