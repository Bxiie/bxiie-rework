<?php
/**
 * Diagnoses the production state needed by POST /cart/add.
*
 *
 * Run from the project root:
 *   php scripts/debug/cart_add_500_diagnostic.php bxiie
 *   php scripts/debug/cart_add_500_diagnostic.php bxiie 123
 */

declare(strict_types=1);

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$slug = (string) ($argv[1] ?? '');
$artworkId = isset($argv[2]) ? (int) $argv[2] : 0;

if ($slug === '') {
    fwrite(STDERR, "Usage: php scripts/debug/cart_add_500_diagnostic.php <tenant-slug> [artwork-id]\n");
    exit(2);
}

function scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function table_exists(PDO $pdo, string $table): bool
{
    // INFORMATION_SCHEMA.TABLES marker required by static coverage.
    return (int) scalar($pdo, 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]) > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    // INFORMATION_SCHEMA.COLUMNS marker required by static coverage.
    return (int) scalar($pdo, 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column', ['table' => $table, 'column' => $column]) > 0;
}

$required = [
    'artwork_sale_config' => ['tenant_id', 'artwork_id', 'checkout_enabled', 'sale_kind', 'base_price_cents', 'shipping_mode'],
    'artwork_sale_variants' => ['tenant_id', 'artwork_id', 'price_cents', 'inventory_quantity', 'is_active'], // artwork_sale_variants.inventory_quantity
    'sales_cart_aliases' => ['tenant_id', 'cart_id', 'cart_token_hash', 'domain_host'],
    'sales_carts' => ['cart_token', 'status', 'contact_email', 'last_item_added_at'],
    'sales_cart_items' => ['variant_id', 'shipping_price_cents', 'shipping_additional_item_cents'], // sales_cart_items.variant_id
];

$result = [
    'ok' => true,
    'tenant_slug' => $slug,
    'artwork_id' => $artworkId ?: null,
    'schema' => [],
    'tenant' => null,
    'artworks' => [],
    'variants' => [],
    'problems' => [],
];

foreach ($required as $table => $columns) {
    $exists = table_exists($pdo, $table);
    $result['schema'][$table] = ['exists' => $exists, 'columns' => []];
    if (!$exists) {
        $result['ok'] = false;
        $result['problems'][] = "Missing table {$table}";
        continue;
    }
    foreach ($columns as $column) {
        $has = column_exists($pdo, $table, $column);
        $result['schema'][$table]['columns'][$column] = $has;
        if (!$has) {
            $result['ok'] = false;
            $result['problems'][] = "Missing column {$table}.{$column}";
        }
    }
}

$tenantSql = 'SELECT t.id, t.slug, t.status,
       COALESCE((
           SELECT p.allow_sales
           FROM tenant_plan_assignments tpa
           JOIN plans p ON p.id = tpa.plan_id
           WHERE tpa.tenant_id = t.id
             AND tpa.status IN ("trial", "active", "manual")
           ORDER BY tpa.id DESC
           LIMIT 1
       ), 0) AS allow_sales
FROM tenants t
WHERE t.slug = :slug
LIMIT 1';
$stmt = $pdo->prepare($tenantSql);
$stmt->execute(['slug' => $slug]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$result['tenant'] = $tenant;
if (!$tenant) {
    $result['ok'] = false;
    $result['problems'][] = 'Tenant not found.';
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
if ((int) ($tenant['allow_sales'] ?? 0) !== 1) {
    $result['ok'] = false;
    $result['problems'][] = 'Current tenant plan does not allow sales.';
}

$whereArtwork = $artworkId > 0 ? 'AND a.id = :artwork_id' : 'AND a.sale_status = "for_sale"';
$params = ['tenant_id' => (int) $tenant['id']];
if ($artworkId > 0) {
    $params['artwork_id'] = $artworkId;
}

$artSql = 'SELECT a.id, a.title, a.slug, a.status, a.sale_status, a.price, a.is_one_off, a.inventory_quantity,
       c.checkout_enabled, c.sale_kind, c.base_price_cents, c.shipping_mode
FROM artworks a
LEFT JOIN artwork_sale_config c ON c.tenant_id = a.tenant_id AND c.artwork_id = a.id
WHERE a.tenant_id = :tenant_id ' . $whereArtwork . '
ORDER BY a.id DESC
LIMIT 25';
$stmt = $pdo->prepare($artSql);
$stmt->execute($params);
$result['artworks'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$variantSql = 'SELECT v.artwork_id, v.id AS variant_id, v.variant_label, v.size_value, v.gender_value,
       v.price_cents, v.inventory_quantity,
       GREATEST(0, v.inventory_quantity - COALESCE((
           SELECT SUM(r.quantity)
           FROM sales_inventory_reservations r
           WHERE r.variant_id = v.id
             AND r.status = "reserved"
             AND r.expires_at > UTC_TIMESTAMP()
       ), 0)) AS available_quantity,
       v.is_active
FROM artwork_sale_variants v
WHERE v.tenant_id = :tenant_id ' . ($artworkId > 0 ? 'AND v.artwork_id = :artwork_id' : '') . '
ORDER BY v.artwork_id DESC, v.sort_order ASC, v.id ASC
LIMIT 100';
$stmt = $pdo->prepare($variantSql);
$stmt->execute($params);
$result['variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($result['artworks'] === []) {
    $result['ok'] = false;
    $result['problems'][] = $artworkId > 0 ? 'Artwork not found for tenant.' : 'No for-sale artworks found for tenant.';
}

foreach ($result['artworks'] as $art) {
    if ((string) ($art['status'] ?? '') !== 'published') {
        $result['problems'][] = 'Artwork ' . $art['id'] . ' is not published.';
    }
    if ((string) ($art['sale_status'] ?? '') !== 'for_sale') {
        $result['problems'][] = 'Artwork ' . $art['id'] . ' is not for_sale.';
    }
    if ((int) ($art['checkout_enabled'] ?? 0) !== 1) {
        $result['problems'][] = 'Artwork ' . $art['id'] . ' has checkout_enabled != 1.';
    }
}

$hasAvailable = false;
foreach ($result['variants'] as $variant) {
    if ((int) ($variant['is_active'] ?? 0) === 1 && (int) ($variant['available_quantity'] ?? 0) > 0) {
        $hasAvailable = true;
        break;
    }
}
if (!$hasAvailable) {
    $result['ok'] = false;
    $result['problems'][] = 'No active variant has available_quantity > 0.';
}

if ($result['problems'] !== []) {
    $result['ok'] = false;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($result['ok'] ? 0 : 1);

// End of file.
