<?php

declare(strict_types=1);

/**
 * Normalize the 14 Northstar Studio training artworks into a deliberate matrix
 * of publication, portfolio, sales, shipping, inventory, media, and curation
 * examples.
 *
 * This script is read-only unless --apply is supplied.
 *
 * Usage:
 *   php scripts/training/seed_training_artwork_metadata.php --dry-run
 *   php scripts/training/seed_training_artwork_metadata.php --apply
 */

use App\Support\Database;

const TRAINING_SLUG = 'training';

/**
 * Print an error and terminate.
 */
function fail(string $message): never
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

/**
 * Load an environment file without overwriting values already supplied by the
 * calling process.
 */
function loadEnvironmentFile(string $path): void
{
    if (!is_readable($path)) {
        fail("Environment file is not readable: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fail("Unable to read environment file: {$path}");
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        if (
            strlen($value) >= 2
            && (
                ($value[0] === '"' && $value[strlen($value) - 1] === '"')
                || ($value[0] === "'" && $value[strlen($value) - 1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

/**
 * Return a single tenant ID for the training slug.
 */
function resolveTenantId(PDO $pdo): int
{
    $statement = $pdo->prepare(
        'SELECT id FROM tenants WHERE slug = :slug ORDER BY id ASC'
    );
    $statement->execute(['slug' => TRAINING_SLUG]);
    $ids = $statement->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) !== 1) {
        throw new RuntimeException(
            sprintf(
                'Expected exactly one tenant with slug "%s"; found %d.',
                TRAINING_SLUG,
                count($ids)
            )
        );
    }

    return (int) $ids[0];
}

/**
 * Return table columns keyed by column name.
 *
 * @return array<string,bool>
 */
function tableColumns(PDO $pdo, string $table): array
{
    $statement = $pdo->prepare(
        'SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table'
    );
    $statement->execute(['table' => $table]);

    return array_fill_keys(
        array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)),
        true
    );
}

/**
 * Confirm that every required column exists before any backup or transaction.
 *
 * @param array<string,list<string>> $requirements
 */
function assertSchema(PDO $pdo, array $requirements): void
{
    foreach ($requirements as $table => $requiredColumns) {
        $available = tableColumns($pdo, $table);
        if ($available === []) {
            throw new RuntimeException("Required table {$table} is missing.");
        }

        foreach ($requiredColumns as $column) {
            if (!isset($available[$column])) {
                throw new RuntimeException(
                    "Required column {$table}.{$column} is missing."
                );
            }
        }
    }
}

/**
 * Return one row keyed by a unique column.
 *
 * @return array<string,mixed>
 */
function requireRow(
    PDO $pdo,
    string $sql,
    array $parameters,
    string $label
): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== 1) {
        throw new RuntimeException(
            sprintf('Expected exactly one %s; found %d.', $label, count($rows))
        );
    }

    return $rows[0];
}

/**
 * Return the 14 expected artwork rows keyed by slug.
 *
 * @return array<string,array<string,mixed>>
 */
function resolveArtworks(PDO $pdo, int $tenantId): array
{
    $slugs = [
        'meridian-no-3',
        'folded-horizon',
        'counterweight',
        'red-shift',
        'quiet-vector',
        'field-notes-i',
        'field-notes-ii',
        'blue-interval',
        'small-orbit',
        'axis-study',
        'trial-assembly',
        'north-wall-proposal',
        'river-geometry',
        'untitled-maquette',
    ];

    $tokens = [];
    $parameters = ['tenant_id' => $tenantId];
    foreach ($slugs as $index => $slug) {
        $name = 'slug_' . $index;
        $tokens[] = ':' . $name;
        $parameters[$name] = $slug;
    }

    $statement = $pdo->prepare(
        'SELECT *
         FROM artworks
         WHERE tenant_id = :tenant_id
           AND slug IN (' . implode(', ', $tokens) . ')
         ORDER BY id ASC'
    );
    $statement->execute($parameters);

    $resolved = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $resolved[(string) $row['slug']] = $row;
    }

    $missing = array_values(array_diff($slugs, array_keys($resolved)));
    if ($missing !== []) {
        throw new RuntimeException(
            'Missing expected training artworks: ' . implode(', ', $missing)
        );
    }

    if (count($resolved) !== 14) {
        throw new RuntimeException(
            sprintf('Expected 14 training artworks; found %d.', count($resolved))
        );
    }

    return $resolved;
}

/**
 * Resolve portfolio sections by slug.
 *
 * @return array<string,int>
 */
function resolveSections(PDO $pdo, int $tenantId): array
{
    $required = [
        'sculpture',
        'works-on-paper',
        'studies-and-editions',
        'archive',
    ];

    $statement = $pdo->prepare(
        'SELECT id, slug
         FROM portfolio_sections
         WHERE tenant_id = :tenant_id'
    );
    $statement->execute(['tenant_id' => $tenantId]);

    $sections = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sections[(string) $row['slug']] = (int) $row['id'];
    }

    foreach ($required as $slug) {
        if (!isset($sections[$slug])) {
            throw new RuntimeException("Missing portfolio section: {$slug}");
        }
    }

    return $sections;
}

/**
 * Resolve shipping profiles by code.
 *
 * @return array<string,int>
 */
function resolveShippingProfiles(PDO $pdo, int $tenantId): array
{
    $required = [
        'small_flat',
        'small_merch',
        'free_shipping',
        'large_quote',
    ];

    $statement = $pdo->prepare(
        'SELECT id, code
         FROM tenant_shipping_profiles
         WHERE tenant_id = :tenant_id'
    );
    $statement->execute(['tenant_id' => $tenantId]);

    $profiles = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $profiles[(string) $row['code']] = (int) $row['id'];
    }

    foreach ($required as $code) {
        if (!isset($profiles[$code])) {
            throw new RuntimeException("Missing shipping profile: {$code}");
        }
    }

    return $profiles;
}

/**
 * Resolve an active tenant user for curation fixture ownership.
 */
function resolveTenantUserId(PDO $pdo, int $tenantId): ?int
{
    foreach ([
        'SELECT user_id
         FROM tenant_users
         WHERE tenant_id = :tenant_id
           AND status = "active"
         ORDER BY FIELD(role, "owner", "admin", "editor", "viewer"), id
         LIMIT 1',
        'SELECT user_id
         FROM tenant_memberships
         WHERE tenant_id = :tenant_id
           AND status = "active"
         ORDER BY id
         LIMIT 1',
    ] as $sql) {
        try {
            $statement = $pdo->prepare($sql);
            $statement->execute(['tenant_id' => $tenantId]);
            $value = $statement->fetchColumn();
            if ($value !== false) {
                return (int) $value;
            }
        } catch (Throwable) {
            // Try the next supported membership table.
        }
    }

    return null;
}

/**
 * Write a JSON backup of every table modified by this script.
 */
function backupRows(
    PDO $pdo,
    int $tenantId,
    array $artworks,
    string $root
): string {
    $base = getenv('ARTSFOLIO_TRAINING_BACKUP_ROOT');
    if ($base === false || trim($base) === '') {
        $candidate = $root . '/storage/training-backups';
        $base = is_dir(dirname($candidate)) && is_writable(dirname($candidate))
            ? $candidate
            : rtrim(sys_get_temp_dir(), '/') . '/artsfolio-training-backups';
    }

    $directory = rtrim((string) $base, '/')
        . '/training-artwork-metadata-'
        . gmdate('YmdHis');

    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create backup directory: {$directory}");
    }

    if (!is_writable($directory)) {
        throw new RuntimeException("Backup directory is not writable: {$directory}");
    }

    $artworkIds = array_map(
        static fn (array $row): int => (int) $row['id'],
        array_values($artworks)
    );
    $idList = implode(',', array_map('intval', $artworkIds));

    $queries = [
        'artworks' => "SELECT * FROM artworks WHERE tenant_id = {$tenantId} AND id IN ({$idList}) ORDER BY id",
        'media_assets' => "SELECT m.* FROM media_assets m JOIN artworks a ON a.primary_media_id = m.id WHERE a.tenant_id = {$tenantId} AND a.id IN ({$idList}) ORDER BY m.id",
        'artwork_sale_config' => "SELECT * FROM artwork_sale_config WHERE tenant_id = {$tenantId} AND artwork_id IN ({$idList}) ORDER BY id",
        'artwork_sale_variants' => "SELECT * FROM artwork_sale_variants WHERE tenant_id = {$tenantId} AND artwork_id IN ({$idList}) ORDER BY id",
        'artwork_section_assignments' => "SELECT * FROM artwork_section_assignments WHERE artwork_id IN ({$idList}) ORDER BY id",
        'portfolio_sections' => "SELECT * FROM portfolio_sections WHERE tenant_id = {$tenantId} ORDER BY id",
        'curation_lists' => "SELECT * FROM curation_lists WHERE tenant_id = {$tenantId} ORDER BY id",
        'curation_items' => "SELECT * FROM curation_items WHERE tenant_id = {$tenantId} ORDER BY id",
    ];

    foreach ($queries as $name => $sql) {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $json = json_encode(
            $rows,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false || file_put_contents("{$directory}/{$name}.json", $json . PHP_EOL) === false) {
            throw new RuntimeException("Unable to write backup for table {$name}.");
        }
    }

    $manifest = [
        'created_at_utc' => gmdate(DATE_ATOM),
        'tenant_slug' => TRAINING_SLUG,
        'tenant_id' => $tenantId,
        'artwork_slugs' => array_keys($artworks),
    ];
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents("{$directory}/manifest.json", $json . PHP_EOL) === false) {
        throw new RuntimeException('Unable to write backup manifest.');
    }

    return $directory;
}

/**
 * Return the complete training metadata plan.
 *
 * @return array<string,array<string,mixed>>
 */
function artworkPlan(array $profiles): array
{
    return [
        'meridian-no-3' => [
            'title' => 'Meridian No. 3',
            'description' => 'A vertical steel construction balanced on a rough stone base, using compressed planes and a narrow red interval to turn weight into rhythm.',
            'medium' => 'Powder-coated steel and Vermont granite',
            'dimensions' => '72 × 24 × 20 in',
            'year_created' => '2026',
            'status' => 'published',
            'sale_status' => 'for_sale',
            'notes' => 'Featured training artwork. One-of-a-kind sculpture; shipping is quoted before purchase.',
            'notes_html' => '<p>Featured training artwork. One-of-a-kind sculpture; shipping is quoted before purchase.</p>',
            'price' => '4800',
            'is_one_off' => 1,
            'inventory_quantity' => 1,
            'sort_order' => 10,
            'sections' => ['sculpture'],
            'sale' => ['one_off', 'none', 'none', 480000, 'none', $profiles['large_quote'], 0, 0, 0, 1],
            'variants' => [
                ['NST-MER-001', 'Original', null, 'not_applicable', $profiles['large_quote'], 480000, 0, 0, 1, 10, 0],
            ],
            'alt' => 'Tall black geometric steel sculpture with a red interior plane on a rough stone base.',
            'caption' => 'Meridian No. 3, 2026. Powder-coated steel and Vermont granite.',
        ],
        'folded-horizon' => [
            'title' => 'Folded Horizon',
            'description' => 'An angular steel form that folds a low horizon line into a compact architectural mass.',
            'medium' => 'Painted steel',
            'dimensions' => '38 × 52 × 18 in',
            'year_created' => '2025',
            'status' => 'published',
            'sale_status' => 'sold',
            'notes' => 'Sold work retained publicly as an example of completed inventory and historical portfolio placement.',
            'notes_html' => '<p>Sold work retained publicly as an example of completed inventory and historical portfolio placement.</p>',
            'price' => 'Sold',
            'is_one_off' => 1,
            'inventory_quantity' => 0,
            'sort_order' => 20,
            'sections' => ['sculpture', 'archive'],
            'sale' => ['one_off', 'none', 'none', null, 'none', null, 0, 0, 0, 1],
            'variants' => [
                ['NST-FOL-001', 'Original — sold', null, 'not_applicable', null, null, 0, 0, 0, 10, 0],
            ],
            'alt' => 'Low angular black steel sculpture forming a folded horizontal profile.',
            'caption' => 'Folded Horizon, 2025. Painted steel. Sold.',
        ],
        'counterweight' => [
            'title' => 'Counterweight',
            'description' => 'A cantilevered construction in which a dense black volume is held in visual suspension by a narrow red support.',
            'medium' => 'Powder-coated steel',
            'dimensions' => '46 × 40 × 16 in',
            'year_created' => '2026',
            'status' => 'published',
            'sale_status' => 'for_sale',
            'notes' => 'One-off sculpture with a listed price and quoted freight. Checkout remains disabled because the shipping profile requires an artist quote.',
            'notes_html' => '<p>One-off sculpture with a listed price and quoted freight. Checkout remains disabled because the shipping profile requires an artist quote.</p>',
            'price' => '3200',
            'is_one_off' => 1,
            'inventory_quantity' => 1,
            'sort_order' => 30,
            'sections' => ['sculpture'],
            'sale' => ['one_off', 'none', 'none', 320000, 'none', $profiles['large_quote'], 0, 0, 0, 1],
            'variants' => [
                ['NST-COU-001', 'Original', null, 'not_applicable', $profiles['large_quote'], 320000, 0, 0, 1, 10, 0],
            ],
            'alt' => 'Black cantilevered geometric sculpture supported by a narrow red vertical element.',
            'caption' => 'Counterweight, 2026. Powder-coated steel.',
        ],
        'red-shift' => [
            'title' => 'Red Shift',
            'description' => 'A developing sculpture study built around a bright red diagonal passing through a black open frame.',
            'medium' => 'Painted steel study',
            'dimensions' => '24 × 18 × 12 in',
            'year_created' => '2026',
            'status' => 'draft',
            'sale_status' => 'nfs',
            'notes' => 'Draft record used to demonstrate unpublished artwork editing.',
            'notes_html' => '<p>Draft record used to demonstrate unpublished artwork editing.</p>',
            'price' => null,
            'is_one_off' => 1,
            'inventory_quantity' => 1,
            'sort_order' => 40,
            'sections' => ['sculpture'],
            'sale' => ['one_off', 'none', 'none', null, 'none', null, 0, 0, 0, 1],
            'variants' => [
                ['NST-RED-DRAFT', 'Draft original', null, 'not_applicable', null, null, 0, 0, 1, 10, 0],
            ],
            'alt' => 'Black open-frame sculpture study crossed by a vivid red diagonal.',
            'caption' => 'Red Shift, work in progress, 2026.',
        ],
        'quiet-vector' => [
            'title' => 'Quiet Vector',
            'description' => 'A restrained archival print in which a single diagonal interrupts a field of pale geometric marks.',
            'medium' => 'Archival pigment print on cotton rag paper',
            'dimensions' => '18 × 24 in',
            'year_created' => '2026',
            'status' => 'published',
            'sale_status' => 'for_sale',
            'notes' => 'Numbered edition of 12. Flat shipping is charged once per order.',
            'notes_html' => '<p>Numbered edition of 12. Flat shipping is charged once per order.</p>',
            'price' => '185',
            'is_one_off' => 0,
            'inventory_quantity' => 12,
            'sort_order' => 50,
            'sections' => ['works-on-paper'],
            'sale' => ['limited_quantity', 'none', 'none', 18500, 'flat_per_order', $profiles['small_flat'], 500, 0, 1, 1],
            'variants' => [
                ['NST-QV-ED12', 'Signed edition', null, 'not_applicable', $profiles['small_flat'], 18500, 500, 0, 12, 10, 1],
            ],
            'alt' => 'Minimal pale geometric print interrupted by a single dark diagonal line.',
            'caption' => 'Quiet Vector, 2026. Archival pigment print, edition of 12.',
        ],
        'field-notes-i' => [
            'title' => 'Field Notes I',
            'description' => 'A compact color study combining a dark field, a red bar, and offset geometric fragments.',
            'medium' => 'Risograph and archival pigment print',
            'dimensions' => '8 × 10 in',
            'year_created' => '2026',
            'status' => 'published',
            'sale_status' => 'for_sale',
            'notes' => 'Open edition with capped combined shipping for small merchandise.',
            'notes_html' => '<p>Open edition with capped combined shipping for small merchandise.</p>',
            'price' => '29.99',
            'is_one_off' => 0,
            'inventory_quantity' => 30,
            'sort_order' => 60,
            'sections' => ['works-on-paper', 'studies-and-editions'],
            'sale' => ['limited_quantity', 'none', 'none', 2999, 'flat_per_order', $profiles['small_merch'], 600, 200, 1, 1],
            'variants' => [
                ['NST-FN1-OPEN', 'Open edition', null, 'not_applicable', $profiles['small_merch'], 2999, 600, 200, 30, 10, 1],
            ],
            'alt' => 'Small abstract print with a black field, red bar, and layered geometric fragments.',
            'caption' => 'Field Notes I, 2026. Risograph and archival pigment print.',
        ],
        'field-notes-ii' => [
            'title' => 'Field Notes II',
            'description' => 'A modular print offered in three paper sizes, demonstrating variant-level pricing and inventory.',
            'medium' => 'Archival pigment print on cotton rag paper',
            'dimensions' => 'Available in three sizes',
            'year_created' => '2026',
            'status' => 'published',
            'sale_status' => 'for_sale',
            'notes' => 'Size variants have independent prices, SKUs, stock, and shipping values.',
            'notes_html' => '<p>Size variants have independent prices, SKUs, stock, and shipping values.</p>',
            'price' => 'From $45',
            'is_one_off' => 0,
            'inventory_quantity' => 18,
            'sort_order' => 70,
            'sections' => ['works-on-paper', 'studies-and-editions'],
            'sale' => ['variant_inventory', 'size_numeric', 'none', null, 'variant', null, 0, 0, 1, 1],
            'variants' => [
                ['NST-FN2-8X10', '8 × 10 in', '8x10', 'not_applicable', $profiles['small_flat'], 4500, 500, 0, 8, 10, 1],
                ['NST-FN2-12X16', '12 × 16 in', '12x16', 'not_applicable', $profiles['small_flat'], 8500, 500, 0, 6, 20, 1],
                ['NST-FN2-18X24', '18 × 24 in', '18x24', 'not_applicable', $profiles['small_merch'], 14500, 700, 200, 4, 30, 1],
            ],
            'alt' => 'Layered geometric print with black, red, and pale gray angular forms.',
            'caption' => 'Field Notes II, 2026. Available in three sizes.',
        ],
        'blue-interval' => [
            'title' => 'Blue Interval',
            'description' => 'A blue and black print study retained as a draft while pricing and publication details are reviewed.',
            'medium' => 'Archival pigment print',
            'dimensions' => '16 × 20 in',
            'year_created' => '2026',
            'status' => 'draft',
            'sale_status' => 'for_sale',
            'notes' => 'Intentional draft-for-sale case: a price exists, but checkout is disabled until publication.',
            'notes_html' => '<p>Intentional draft-for-sale case: a price exists, but checkout is disabled until publication.</p>',
            'price' => '120',
            'is_one_off' => 0,
            'inventory_quantity' => 6,
            'sort_order' => 80,
            'sections' => ['works-on-paper'],
            'sale' => ['limited_quantity', 'none', 'none', 12000, 'flat_per_order', $profiles['small_flat'], 500, 0, 0, 1],
            'variants' => [
                ['NST-BLU-ED6', 'Edition of 6', null, 'not_applicable', $profiles['small_flat'], 12000, 500, 0, 6, 10, 0],
            ],
            'alt' => 'Abstract blue and black geometric print with a narrow pale interval.',
            'caption' => 'Blue Interval, 2026. Draft record.',
        ],
        'small-orbit' => [
            'title' => 'Small Orbit',
            'description' => 'A tabletop steel sculpture offered in three finish variants, each with independent stock and pricing.',
            'medium' => 'Steel with selectable finish',
            'dimensions' => '14 × 12 × 10 in',
            'year_created' => '2026',
            'status' => 'published',
            'sale_status' => 'for_sale',
            'notes' => 'Custom finish variants demonstrate variant inventory and free shipping.',
            'notes_html' => '<p>Custom finish variants demonstrate variant inventory and free shipping.</p>',
            'price' => 'From $425',
            'is_one_off' => 0,
            'inventory_quantity' => 9,
            'sort_order' => 90,
            'sections' => ['sculpture', 'studies-and-editions'],
            'sale' => ['variant_inventory', 'custom', 'none', null, 'variant', null, 0, 0, 1, 1],
            'variants' => [
                ['NST-ORB-BLK', 'Blackened steel', 'Black', 'not_applicable', $profiles['free_shipping'], 42500, 0, 0, 4, 10, 1],
                ['NST-ORB-RED', 'Signal red', 'Red', 'not_applicable', $profiles['free_shipping'], 45000, 0, 0, 3, 20, 1],
                ['NST-ORB-BLU', 'Deep blue', 'Blue', 'not_applicable', $profiles['free_shipping'], 45000, 0, 0, 2, 30, 1],
            ],
            'alt' => 'Compact circular steel sculpture with intersecting geometric planes.',
            'caption' => 'Small Orbit, 2026. Steel with selectable finish.',
        ],
        'axis-study' => [
            'title' => 'Axis Study',
            'description' => 'A non-sale studio study documenting the intersection of two offset axes.',
            'medium' => 'Painted wood and steel',
            'dimensions' => '20 × 16 × 14 in',
            'year_created' => '2025',
            'status' => 'published',
            'sale_status' => 'nfs',
            'notes' => 'Published inquiry-only artwork with no price or checkout.',
            'notes_html' => '<p>Published inquiry-only artwork with no price or checkout.</p>',
            'price' => null,
            'is_one_off' => 1,
            'inventory_quantity' => 1,
            'sort_order' => 100,
            'sections' => ['studies-and-editions'],
            'sale' => ['one_off', 'none', 'none', null, 'none', null, 0, 0, 0, 0],
            'variants' => [
                ['NST-AXIS-NFS', 'Not for sale', null, 'not_applicable', null, null, 0, 0, 1, 10, 0],
            ],
            'alt' => 'Small black geometric study formed from two offset axes.',
            'caption' => 'Axis Study, 2025. Painted wood and steel. Not for sale.',
        ],
        'trial-assembly' => [
            'title' => 'Trial Assembly',
            'description' => 'An incomplete fabrication test retained without a primary image to demonstrate missing-image filtering.',
            'medium' => 'Steel fabrication test',
            'dimensions' => 'Variable',
            'year_created' => '2026',
            'status' => 'draft',
            'sale_status' => 'nfs',
            'notes' => 'Intentional missing-primary-image case. The uploaded media asset remains in the library.',
            'notes_html' => '<p>Intentional missing-primary-image case. The uploaded media asset remains in the library.</p>',
            'price' => null,
            'is_one_off' => 1,
            'inventory_quantity' => 1,
            'sort_order' => 110,
            'sections' => ['studies-and-editions'],
            'sale' => ['one_off', 'none', 'none', null, 'none', null, 0, 0, 0, 0],
            'variants' => [
                ['NST-TRIAL-DRAFT', 'Draft study', null, 'not_applicable', null, null, 0, 0, 1, 10, 0],
            ],
            'alt' => 'Black geometric fabrication test photographed against a neutral background.',
            'caption' => 'Trial Assembly, 2026. Draft fabrication test.',
            'clear_primary_media' => true,
        ],
        'north-wall-proposal' => [
            'title' => 'North Wall Proposal',
            'description' => 'A proposal image for a suspended wall installation, retained as an archived project record.',
            'medium' => 'Digital proposal rendering',
            'dimensions' => 'Site-specific',
            'year_created' => '2024',
            'status' => 'archived',
            'sale_status' => 'nfs',
            'notes' => 'Archived artwork used to demonstrate historical records and archived-status filters.',
            'notes_html' => '<p>Archived artwork used to demonstrate historical records and archived-status filters.</p>',
            'price' => null,
            'is_one_off' => 1,
            'inventory_quantity' => 0,
            'sort_order' => 120,
            'sections' => ['archive'],
            'sale' => ['one_off', 'none', 'none', null, 'none', null, 0, 0, 0, 0],
            'variants' => [
                ['NST-NWP-ARCH', 'Archived proposal', null, 'not_applicable', null, null, 0, 0, 0, 10, 0],
            ],
            'alt' => 'Architectural rendering of a black geometric installation proposed for a long wall.',
            'caption' => 'North Wall Proposal, 2024. Archived project.',
        ],
        'river-geometry' => [
            'title' => 'River Geometry',
            'description' => 'An outdoor steel sculpture whose angled planes frame changing views of water and shoreline.',
            'medium' => 'Weathering steel',
            'dimensions' => '96 × 72 × 48 in',
            'year_created' => '2023',
            'status' => 'published',
            'sale_status' => 'sold',
            'notes' => 'Sold outdoor work retained in the Archive section.',
            'notes_html' => '<p>Sold outdoor work retained in the Archive section.</p>',
            'price' => 'Sold',
            'is_one_off' => 1,
            'inventory_quantity' => 0,
            'sort_order' => 130,
            'sections' => ['archive'],
            'sale' => ['one_off', 'none', 'none', null, 'none', null, 0, 0, 0, 1],
            'variants' => [
                ['NST-RIV-SOLD', 'Original — sold', null, 'not_applicable', null, null, 0, 0, 0, 10, 0],
            ],
            'alt' => 'Large weathering-steel geometric sculpture installed beside a river.',
            'caption' => 'River Geometry, 2023. Weathering steel. Sold.',
        ],
        'untitled-maquette' => [
            'title' => 'Untitled Maquette',
            'description' => 'A small unresolved maquette awaiting title, section placement, and publication review.',
            'medium' => 'Painted wood and card',
            'dimensions' => '9 × 7 × 6 in',
            'year_created' => '2026',
            'status' => 'draft',
            'sale_status' => 'nfs',
            'notes' => 'Intentional no-section record for placement and filter demonstrations.',
            'notes_html' => '<p>Intentional no-section record for placement and filter demonstrations.</p>',
            'price' => null,
            'is_one_off' => 1,
            'inventory_quantity' => 1,
            'sort_order' => 140,
            'sections' => [],
            'sale' => ['one_off', 'none', 'none', null, 'none', null, 0, 0, 0, 0],
            'variants' => [
                ['NST-MAQ-DRAFT', 'Draft maquette', null, 'not_applicable', null, null, 0, 0, 1, 10, 0],
            ],
            'alt' => 'Small black and red geometric maquette on a neutral surface.',
            'caption' => 'Untitled Maquette, 2026. Draft record.',
        ],
    ];
}

/**
 * Apply artwork and linked metadata.
 */
function applyPlan(
    PDO $pdo,
    int $tenantId,
    array $artworks,
    array $sections,
    array $plan
): void {
    $updateArtwork = $pdo->prepare(
        'UPDATE artworks
         SET title = :title,
             description = :description,
             medium = :medium,
             dimensions = :dimensions,
             year_created = :year_created,
             status = :status,
             sale_status = :sale_status,
             notes = :notes,
             notes_html = :notes_html,
             price = :price,
             is_one_off = :is_one_off,
             inventory_quantity = :inventory_quantity,
             sort_order = :sort_order,
             primary_media_id = :primary_media_id,
             updated_at = UTC_TIMESTAMP()
         WHERE tenant_id = :tenant_id
           AND id = :artwork_id'
    );

    $updateMedia = $pdo->prepare(
        'UPDATE media_assets
         SET alt_text = :alt_text,
             title = :title,
             caption = :caption,
             credit = :credit,
             updated_at = UTC_TIMESTAMP()
         WHERE tenant_id = :tenant_id
           AND id = :media_id'
    );

    $updateSale = $pdo->prepare(
        'UPDATE artwork_sale_config
         SET sale_kind = :sale_kind,
             option_schema = :option_schema,
             gender_schema = :gender_schema,
             base_price_cents = :base_price_cents,
             currency = "usd",
             shipping_mode = :shipping_mode,
             shipping_profile_id = :shipping_profile_id,
             shipping_price_cents = :shipping_price_cents,
             shipping_additional_item_cents = :shipping_additional_item_cents,
             ships_to_countries_json = :ships_to_countries_json,
             checkout_enabled = :checkout_enabled,
             require_shipping_address = :require_shipping_address,
             updated_at = UTC_TIMESTAMP()
         WHERE tenant_id = :tenant_id
           AND artwork_id = :artwork_id'
    );

    $deleteSections = $pdo->prepare(
        'DELETE FROM artwork_section_assignments WHERE artwork_id = :artwork_id'
    );
    $insertSection = $pdo->prepare(
        'INSERT INTO artwork_section_assignments (
            artwork_id, section_id, sort_order, created_at
         ) VALUES (
            :artwork_id, :section_id, :sort_order, UTC_TIMESTAMP()
         )'
    );

    $deleteVariants = $pdo->prepare(
        'DELETE FROM artwork_sale_variants
         WHERE tenant_id = :tenant_id
           AND artwork_id = :artwork_id'
    );
    $insertVariant = $pdo->prepare(
        'INSERT INTO artwork_sale_variants (
            tenant_id, artwork_id, sku, variant_label, size_value, gender_value,
            shipping_profile_id, price_cents, shipping_price_cents,
            shipping_additional_item_cents, inventory_quantity,
            original_inventory_quantity, sort_order, is_active,
            created_at, updated_at
         ) VALUES (
            :tenant_id, :artwork_id, :sku, :variant_label, :size_value,
            :gender_value, :shipping_profile_id, :price_cents,
            :shipping_price_cents, :shipping_additional_item_cents,
            :inventory_quantity, :original_inventory_quantity,
            :sort_order, :is_active, UTC_TIMESTAMP(), UTC_TIMESTAMP()
         )'
    );

    $sectionOrder = 10;

    foreach ($plan as $slug => $metadata) {
        $artwork = $artworks[$slug];
        $primaryMediaId = !empty($metadata['clear_primary_media'])
            ? null
            : ($artwork['primary_media_id'] !== null
                ? (int) $artwork['primary_media_id']
                : null);

        $updateArtwork->execute([
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'medium' => $metadata['medium'],
            'dimensions' => $metadata['dimensions'],
            'year_created' => $metadata['year_created'],
            'status' => $metadata['status'],
            'sale_status' => $metadata['sale_status'],
            'notes' => $metadata['notes'],
            'notes_html' => $metadata['notes_html'],
            'price' => $metadata['price'],
            'is_one_off' => $metadata['is_one_off'],
            'inventory_quantity' => $metadata['inventory_quantity'],
            'sort_order' => $metadata['sort_order'],
            'primary_media_id' => $primaryMediaId,
            'tenant_id' => $tenantId,
            'artwork_id' => (int) $artwork['id'],
        ]);

        if ($artwork['primary_media_id'] !== null) {
            $updateMedia->execute([
                'alt_text' => $metadata['alt'],
                'title' => $metadata['title'],
                'caption' => $metadata['caption'],
                'credit' => 'Northstar Studio training asset generated for ArtsFolio',
                'tenant_id' => $tenantId,
                'media_id' => (int) $artwork['primary_media_id'],
            ]);
        }

        [$saleKind, $optionSchema, $genderSchema, $basePrice, $shippingMode,
            $shippingProfileId, $shippingPrice, $additionalPrice,
            $checkoutEnabled, $requireAddress] = $metadata['sale'];

        $updateSale->execute([
            'sale_kind' => $saleKind,
            'option_schema' => $optionSchema,
            'gender_schema' => $genderSchema,
            'base_price_cents' => $basePrice,
            'shipping_mode' => $shippingMode,
            'shipping_profile_id' => $shippingProfileId,
            'shipping_price_cents' => $shippingPrice,
            'shipping_additional_item_cents' => $additionalPrice,
            'ships_to_countries_json' => json_encode(['US', 'CA']),
            'checkout_enabled' => $checkoutEnabled,
            'require_shipping_address' => $requireAddress,
            'tenant_id' => $tenantId,
            'artwork_id' => (int) $artwork['id'],
        ]);

        $deleteSections->execute(['artwork_id' => (int) $artwork['id']]);
        foreach ($metadata['sections'] as $sectionSlug) {
            $insertSection->execute([
                'artwork_id' => (int) $artwork['id'],
                'section_id' => $sections[$sectionSlug],
                'sort_order' => $sectionOrder,
            ]);
            $sectionOrder += 10;
        }

        $deleteVariants->execute([
            'tenant_id' => $tenantId,
            'artwork_id' => (int) $artwork['id'],
        ]);

        foreach ($metadata['variants'] as $variant) {
            $insertVariant->execute([
                'tenant_id' => $tenantId,
                'artwork_id' => (int) $artwork['id'],
                'sku' => $variant[0],
                'variant_label' => $variant[1],
                'size_value' => $variant[2],
                'gender_value' => $variant[3],
                'shipping_profile_id' => $variant[4],
                'price_cents' => $variant[5],
                'shipping_price_cents' => $variant[6],
                'shipping_additional_item_cents' => $variant[7],
                'inventory_quantity' => $variant[8],
                'original_inventory_quantity' => $variant[8],
                'sort_order' => $variant[9],
                'is_active' => $variant[10],
            ]);
        }
    }

    $sectionMetadata = [
        'sculpture' => ['Sculpture', 'Three-dimensional works, maquettes, and constructed forms.', 1, 10, 'active'],
        'works-on-paper' => ['Works on Paper', 'Prints, drawings, and editioned paper works.', 1, 20, 'active'],
        'studies-and-editions' => ['Studies and Editions', 'Maquettes, studies, variants, and editioned objects.', 1, 30, 'active'],
        'archive' => ['Archive', 'Sold, completed, and historical projects retained for reference.', 1, 40, 'active'],
    ];
    $updateSection = $pdo->prepare(
        'UPDATE portfolio_sections
         SET name = :name,
             description = :description,
             show_as_tab = :show_as_tab,
             sort_order = :sort_order,
             status = :status,
             updated_at = UTC_TIMESTAMP()
         WHERE tenant_id = :tenant_id
           AND id = :section_id'
    );
    foreach ($sectionMetadata as $slug => $values) {
        $updateSection->execute([
            'name' => $values[0],
            'description' => $values[1],
            'show_as_tab' => $values[2],
            'sort_order' => $values[3],
            'status' => $values[4],
            'tenant_id' => $tenantId,
            'section_id' => $sections[$slug],
        ]);
    }

}

/**
 * Add a representative set of curation items when an active user exists.
 */
function applyCuration(
    PDO $pdo,
    int $tenantId,
    array $artworks,
    ?int $userId
): void {
    if ($userId === null) {
        echo "[WARN] No active tenant user was found; curation items were skipped." . PHP_EOL;
        return;
    }

    $list = requireRow(
        $pdo,
        'SELECT id
         FROM curation_lists
         WHERE tenant_id = :tenant_id
           AND is_central = 1
         ORDER BY id ASC',
        ['tenant_id' => $tenantId],
        'central curation list'
    );
    $listId = (int) $list['id'];

    $slugs = [
        'meridian-no-3' => ['published', 'Strong hero image and complete metadata.', true],
        'red-shift' => ['reviewing', 'Review publication readiness and final description.', true],
        'trial-assembly' => ['declined', 'Hold until a primary image has been selected.', true],
        'untitled-maquette' => ['queued', 'Needs title confirmation and portfolio placement.', false],
    ];

    $artworkIds = array_map(
        static fn (string $slug): int => (int) $artworks[$slug]['id'],
        array_keys($slugs)
    );
    $tokens = implode(',', array_map('intval', $artworkIds));

    $pdo->exec(
        "DELETE FROM curation_items
         WHERE tenant_id = {$tenantId}
           AND list_id = {$listId}
           AND artwork_id IN ({$tokens})"
    );

    $insert = $pdo->prepare(
        'INSERT INTO curation_items (
            tenant_id, list_id, artwork_id, submitted_by_user_id,
            note, status, reviewed_by_user_id, reviewed_at,
            created_at, updated_at
         ) VALUES (
            :tenant_id, :list_id, :artwork_id, :submitted_by_user_id,
            :note, :status, :reviewed_by_user_id, :reviewed_at,
            UTC_TIMESTAMP(), UTC_TIMESTAMP()
         )'
    );

    foreach ($slugs as $slug => $fixture) {
        $reviewed = $fixture[2];
        $insert->execute([
            'tenant_id' => $tenantId,
            'list_id' => $listId,
            'artwork_id' => (int) $artworks[$slug]['id'],
            'submitted_by_user_id' => $userId,
            'note' => $fixture[1],
            'status' => $fixture[0],
            'reviewed_by_user_id' => $reviewed ? $userId : null,
            'reviewed_at' => $reviewed ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }
}

/**
 * Verify the principal training cases after the update.
 */
function verify(PDO $pdo, int $tenantId): void
{
    $checks = [
        ['published artwork', 'SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "published" AND slug IN ("meridian-no-3","folded-horizon","counterweight","quiet-vector","field-notes-i","field-notes-ii","small-orbit","axis-study","river-geometry")', 9],
        ['draft artwork', 'SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "draft" AND slug IN ("red-shift","blue-interval","trial-assembly","untitled-maquette")', 4],
        ['archived artwork', 'SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND status = "archived" AND slug = "north-wall-proposal"', 1],
        ['variant inventory configuration', 'SELECT COUNT(*) FROM artwork_sale_config WHERE tenant_id = :tenant_id AND sale_kind = "variant_inventory"', 2],
        ['checkout-enabled configuration', 'SELECT COUNT(*) FROM artwork_sale_config WHERE tenant_id = :tenant_id AND checkout_enabled = 1', 4],
        ['multi-variant rows', 'SELECT COUNT(*) FROM artwork_sale_variants WHERE tenant_id = :tenant_id AND is_active = 1', 8],
        ['intentional missing primary image', 'SELECT COUNT(*) FROM artworks WHERE tenant_id = :tenant_id AND slug = "trial-assembly" AND primary_media_id IS NULL', 1],
    ];

    foreach ($checks as [$label, $sql, $expected]) {
        $statement = $pdo->prepare($sql);
        $statement->execute(['tenant_id' => $tenantId]);
        $actual = (int) $statement->fetchColumn();

        if ($actual !== $expected) {
            throw new RuntimeException(
                "Verification failed for {$label}: expected {$expected}, found {$actual}."
            );
        }

        echo "[PASS] Verified {$label}: {$actual}." . PHP_EOL;
    }
}

$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true) || !$apply;

$root = getenv('ARTSFOLIO_ROOT');
if ($root === false || trim($root) === '') {
    $root = dirname(__DIR__, 2);
}
$root = rtrim((string) $root, '/');

$environmentFile = getenv('ARTSFOLIO_ENV_FILE');
if ($environmentFile !== false && trim($environmentFile) !== '') {
    loadEnvironmentFile((string) $environmentFile);
}

$autoload = $root . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    fail("Composer autoloader is not readable: {$autoload}");
}
require $autoload;

try {
    $pdo = Database::connect($root);
    $tenantId = resolveTenantId($pdo);

    assertSchema($pdo, [
        'artworks' => ['id', 'tenant_id', 'primary_media_id', 'slug', 'description', 'medium', 'dimensions', 'year_created', 'status', 'sale_status', 'notes', 'price', 'is_one_off', 'inventory_quantity', 'sort_order', 'notes_html'],
        'media_assets' => ['id', 'tenant_id', 'alt_text', 'title', 'caption', 'credit'],
        'portfolio_sections' => ['id', 'tenant_id', 'slug', 'description', 'show_as_tab', 'sort_order', 'status'],
        'artwork_section_assignments' => ['artwork_id', 'section_id', 'sort_order'],
        'artwork_sale_config' => ['tenant_id', 'artwork_id', 'sale_kind', 'option_schema', 'gender_schema', 'base_price_cents', 'shipping_mode', 'shipping_profile_id', 'shipping_price_cents', 'shipping_additional_item_cents', 'checkout_enabled', 'require_shipping_address'],
        'artwork_sale_variants' => ['tenant_id', 'artwork_id', 'sku', 'variant_label', 'size_value', 'gender_value', 'shipping_profile_id', 'price_cents', 'shipping_price_cents', 'shipping_additional_item_cents', 'inventory_quantity', 'original_inventory_quantity', 'sort_order', 'is_active'],
        'tenant_shipping_profiles' => ['id', 'tenant_id', 'code'],
        'curation_lists' => ['id', 'tenant_id', 'is_central'],
        'curation_items' => ['tenant_id', 'list_id', 'artwork_id', 'submitted_by_user_id', 'note', 'status', 'reviewed_by_user_id', 'reviewed_at'],
    ]);

    $artworks = resolveArtworks($pdo, $tenantId);
    $sections = resolveSections($pdo, $tenantId);
    $profiles = resolveShippingProfiles($pdo, $tenantId);
    $userId = resolveTenantUserId($pdo, $tenantId);
    $plan = artworkPlan($profiles);

    echo "[PASS] Resolved training tenant ID {$tenantId}." . PHP_EOL;
    echo "[PASS] Resolved 14 artwork records, 4 sections, and 4 shipping profiles." . PHP_EOL;
    echo "[PLAN] Publication states: 9 published, 4 draft, 1 archived." . PHP_EOL;
    echo "[PLAN] Sales: one-off, limited quantity, and variant inventory." . PHP_EOL;
    echo "[PLAN] Shipping: quoted, combined flat, capped, variant, and free." . PHP_EOL;
    echo "[PLAN] Portfolio: single-section, multi-section, and no-section cases." . PHP_EOL;
    echo "[PLAN] Media: complete alt/caption metadata plus one missing-primary-image case." . PHP_EOL;
    echo "[PLAN] Site-type branding artworks remain unchanged." . PHP_EOL;
    echo "[PLAN] Homepage assignments are not changed because the tenant UI does not manage them." . PHP_EOL;

    if ($dryRun) {
        echo "[DRY-RUN] No database changes were made. Rerun with --apply." . PHP_EOL;
        exit(0);
    }

    $backupDir = backupRows($pdo, $tenantId, $artworks, $root);
    echo "[PASS] Backed up current training artwork records to {$backupDir}." . PHP_EOL;

    $pdo->beginTransaction();
    applyPlan($pdo, $tenantId, $artworks, $sections, $plan);
    applyCuration($pdo, $tenantId, $artworks, $userId);
    verify($pdo, $tenantId);
    $pdo->commit();

    echo "[PASS] Training artwork metadata deployed for tenant training." . PHP_EOL;
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail($exception->getMessage());
}

// End of file.
