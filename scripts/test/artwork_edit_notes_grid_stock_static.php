<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php';
$controller = file_get_contents($controllerPath);

$failures = [];

function labelBlockForField(string $source, string $fieldName): ?string
{
    $pattern = '/name="' . preg_quote($fieldName, '/') . '"/';
    if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return null;
    }

    $fieldPos = (int) $match[0][1];
    $labelStart = strrpos(substr($source, 0, $fieldPos), '<label');
    $labelEnd = strpos($source, '</label>', $fieldPos);

    if ($labelStart === false || $labelEnd === false) {
        return null;
    }

    return substr($source, $labelStart, $labelEnd + strlen('</label>') - $labelStart);
}

$internalLabel = labelBlockForField($controller, 'notes');
$publicLabel = labelBlockForField($controller, 'notes_html');

if ($internalLabel === null) {
    $failures[] = 'Legacy/private notes field must still use name="notes".';
} else {
    if (strpos($internalLabel, 'Internal notes') === false) {
        $failures[] = 'Legacy/private notes field must be labelled Internal notes.';
    }

    if (strpos($internalLabel, 'Public notes HTML') !== false) {
        $failures[] = 'Legacy/private notes field must not be labelled as Public notes HTML.';
    }
}

if ($publicLabel === null) {
    $failures[] = 'Public notes field must use name="notes_html".';
} else {
    if (strpos($publicLabel, 'Public notes HTML') === false) {
        $failures[] = 'Public notes field must be labelled Public notes HTML.';
    }
}

$publicFieldPos = strpos($controller, 'name="notes_html"');
$sectionFieldsetPos = strpos($controller, '<legend>Portfolio sections</legend>');
if ($publicFieldPos === false || $sectionFieldsetPos === false || $publicFieldPos > $sectionFieldsetPos) {
    $failures[] = 'Public notes field should appear near the top of the artwork edit page before portfolio sections.';
}

$hasAlphabeticSections = strpos($controller, 'ORDER BY LOWER(name), name, id') !== false
    || strpos($controller, 'ORDER BY ps.name ASC, ps.sort_order ASC') !== false;
if (!$hasAlphabeticSections) {
    $failures[] = 'Portfolio sections should be ordered alphabetically on artwork edit pages.';
}

$hasThumbnailEditLink = strpos($controller, '/admin/artworks/edit?id=') !== false
    && strpos($controller, 'return_to') !== false;
if (!$hasThumbnailEditLink) {
    $failures[] = 'Artwork grid thumbnails should link to edit pages and preserve return_to.';
}

$hasSaveReturn = strpos($controller, 'return_to') !== false
    && strpos($controller, '#artwork-') !== false;
if (!$hasSaveReturn) {
    $failures[] = 'Saving artwork should return to the originating artwork grid page and anchor the edited artwork.';
}

if (strpos($controller, 'status <>  ORDER BY') !== false || strpos($controller, "ASC'archived'") !== false || strpos($controller, 'final //') !== false) {
    $failures[] = 'ArtworksController contains corrupted marker or SQL fragments.';
}

if ($failures !== []) {
    fwrite(STDERR, "Artwork edit notes/grid static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "Artwork edit notes/grid static checks passed." . PHP_EOL;

// End of file.
