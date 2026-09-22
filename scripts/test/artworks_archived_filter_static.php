<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/Tenant/Admin/ArtworksController.php');
$required = [
    '$showArchived = ($_GET[\'show_archived\'] ?? \'\') === \'1\' || $statusFilter === \'archived\';',
    'if (!$showArchived) {',
    '$where .= " AND a.status <> \'archived\'";',
    '\'show_archived\' => $showArchived ? \'1\' : \'\',',
    'name="show_archived" value="1"{$showArchivedChecked}> Show archived items',
    'SELECT COUNT(*) FROM artworks a WHERE {$where}',
    'WHERE {$where}',
];
foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException('Missing archived filter behavior: ' . $needle);
    }
}
echo "Artworks archived filter checks passed.\n";
