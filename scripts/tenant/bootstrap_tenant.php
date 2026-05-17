<?php

declare(strict_types=1);

/**
 * Bootstrap a tenant, tenant domains, first admin membership, starter sections, and lifecycle email seed rows.
 *
 * Usage:
 *
 *   ARTSFOLIO_ENV_FILE=.env.local php scripts/tenant/bootstrap_tenant.php \
 *     --slug=bxiie \
 *     --name="Bxiie" \
 *     --domain=bxiie.com \
 *     --domain=www.bxiie.com \
 *     --admin-email=password-auth-test@example.test \
 *     --admin-name="Bxiie Admin"
 */

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$options = getopt('', [
    'slug:',
    'name:',
    'domain::',
    'admin-email:',
    'admin-name::',
    'send-welcome::',
]);

$slug = trim((string) ($options['slug'] ?? ''));
$name = trim((string) ($options['name'] ?? ''));
$adminEmail = strtolower(trim((string) ($options['admin-email'] ?? '')));
$adminName = trim((string) ($options['admin-name'] ?? 'Tenant Admin'));
$sendWelcome = ((string) ($options['send-welcome'] ?? '1')) !== '0';

$domains = $options['domain'] ?? [];
if (is_string($domains)) {
    $domains = [$domains];
}

$domains = array_values(array_filter(array_map(static fn (string $domain): string => strtolower(trim($domain)), $domains)));

if ($slug === '' || $name === '' || $adminEmail === '') {
    fwrite(STDERR, "Required: --slug, --name, --admin-email\n");
    exit(1);
}

if ($domains === []) {
    fwrite(STDERR, "At least one --domain is required.\n");
    exit(1);
}

function tableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $columns = [];

    foreach ($stmt->fetchAll() as $row) {
        $columns[(string) $row['Field']] = true;
    }

    return $columns;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function insertWithKnownColumns(PDO $pdo, string $table, array $values, array $updateColumns = []): void
{
    $columns = tableColumns($pdo, $table);
    $filtered = [];

    foreach ($values as $key => $value) {
        if (isset($columns[$key])) {
            $filtered[$key] = $value;
        }
    }

    if ($filtered === []) {
        throw new RuntimeException("No insertable columns for {$table}");
    }

    $fieldSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", array_keys($filtered)));
    $paramSql = implode(', ', array_map(static fn (string $column): string => ":{$column}", array_keys($filtered)));

    $updates = [];
    foreach ($updateColumns as $column) {
        if (isset($filtered[$column]) && isset($columns[$column])) {
            $updates[] = "`{$column}` = VALUES(`{$column}`)";
        }
    }

    if (isset($columns['updated_at'])) {
        $updates[] = '`updated_at` = CURRENT_TIMESTAMP';
    }

    $sql = "INSERT INTO `{$table}` ({$fieldSql}) VALUES ({$paramSql})";
    if ($updates !== []) {
        $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($filtered);
}

function findRoleId(PDO $pdo, array $roleNames): ?int
{
    if (!tableExists($pdo, 'roles')) {
        return null;
    }

    $placeholders = implode(', ', array_fill(0, count($roleNames), '?'));
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name IN ({$placeholders}) ORDER BY id LIMIT 1");
    $stmt->execute($roleNames);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

$pdo->beginTransaction();

try {
    insertWithKnownColumns($pdo, 'tenants', [
        'uuid' => $pdo->query('SELECT UUID()')->fetchColumn(),
        'slug' => $slug,
        'name' => $name,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ], ['name', 'status']);

    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $tenantId = (int) $stmt->fetchColumn();

    if ($tenantId <= 0) {
        throw new RuntimeException("Could not resolve tenant after insert: {$slug}");
    }

    foreach ($domains as $domain) {
        insertWithKnownColumns($pdo, 'tenant_domains', [
            'uuid' => $pdo->query('SELECT UUID()')->fetchColumn(),
            'tenant_id' => $tenantId,
            'hostname' => $domain,
            'domain' => $domain,
            'status' => 'active',
            'domain_type' => str_ends_with($domain, '.artsfol.io') ? 'platform_subdomain' : 'custom',
            'is_primary_domain' => $domain === $domains[0] ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['tenant_id', 'status', 'domain_type', 'is_primary_domain']);
    }

    insertWithKnownColumns($pdo, 'users', [
        'uuid' => $pdo->query('SELECT UUID()')->fetchColumn(),
        'email' => $adminEmail,
        'name' => $adminName,
        'display_name' => $adminName,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ], ['name', 'display_name', 'status']);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $adminEmail]);
    $userId = (int) $stmt->fetchColumn();

    if ($userId <= 0) {
        throw new RuntimeException("Could not resolve user after insert: {$adminEmail}");
    }

    if (tableExists($pdo, 'tenant_memberships')) {
        insertWithKnownColumns($pdo, 'tenant_memberships', [
            'uuid' => $pdo->query('SELECT UUID()')->fetchColumn(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['status']);
    }

    $tenantOwnerRoleId = findRoleId($pdo, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);

    if ($tenantOwnerRoleId !== null && tableExists($pdo, 'role_assignments')) {
        insertWithKnownColumns($pdo, 'role_assignments', [
            'uuid' => $pdo->query('SELECT UUID()')->fetchColumn(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role_id' => $tenantOwnerRoleId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], []);
    }

    if (tableExists($pdo, 'portfolio_sections')) {
        foreach (['Featured', 'Portfolio', 'About'] as $position => $sectionName) {
            insertWithKnownColumns($pdo, 'portfolio_sections', [
                'uuid' => $pdo->query('SELECT UUID()')->fetchColumn(),
                'tenant_id' => $tenantId,
                'name' => $sectionName,
                'title' => $sectionName,
                'slug' => strtolower($sectionName),
                'sort_order' => $position + 1,
                'display_order' => $position + 1,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['name', 'title', 'sort_order', 'display_order', 'status']);
        }
    }

    if ($sendWelcome && tableExists($pdo, 'email_outbox')) {
        $outboxColumns = tableColumns($pdo, 'email_outbox');
        $payload = [
            'template' => 'tenant_admin_welcome_6h',
            'tenant_slug' => $slug,
            'admin_email' => $adminEmail,
        ];

        $values = [
            'uuid' => $pdo->query('SELECT UUID()')->fetchColumn(),
            'tenant_id' => $tenantId,
            'recipient_email' => $adminEmail,
            'to_email' => $adminEmail,
            'subject' => "Welcome to ArtsFolio, {$name}",
            'body_text' => "Welcome to ArtsFolio. Your tenant {$name} has been created.",
            'body_html' => "<p>Welcome to ArtsFolio. Your tenant <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong> has been created.</p>",
            'template_key' => 'tenant_admin_welcome_6h',
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'queued',
            'available_at' => date('Y-m-d H:i:s', time() + 21600),
            'send_after' => date('Y-m-d H:i:s', time() + 21600),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($outboxColumns['recipient_email']) || isset($outboxColumns['to_email'])) {
            insertWithKnownColumns($pdo, 'email_outbox', $values, []);
        }
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'tenant_id' => $tenantId,
        'slug' => $slug,
        'domains' => $domains,
        'admin_user_id' => $userId,
        'admin_email' => $adminEmail,
        'welcome_email_queued_if_supported' => $sendWelcome,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    $pdo->rollBack();

    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

// End of file.
