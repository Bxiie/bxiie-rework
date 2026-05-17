<?php

declare(strict_types=1);

/**
 * Diagnoses and repairs the local test user's membership for the bxiie tenant.
 */

use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);

$email = 'password-auth-test@example.test';
$tenantSlug = 'bxiie';

$userStmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email LIMIT 1");
$userStmt->execute(['email' => $email]);
$user = $userStmt->fetch();

if (!$user) {
    fwrite(STDERR, "Missing test user {$email}. Run php scripts/test/password_auth.php first.\n");
    exit(1);
}

$tenantStmt = $pdo->prepare("SELECT id, slug, name FROM tenants WHERE slug = :slug LIMIT 1");
$tenantStmt->execute(['slug' => $tenantSlug]);
$tenant = $tenantStmt->fetch();

if (!$tenant) {
    fwrite(STDERR, "Missing tenant slug {$tenantSlug}.\n");
    exit(1);
}

$roleStmt = $pdo->prepare("SELECT id, name FROM roles WHERE name IN ('tenant_owner', 'tenant_admin', 'tenant editor', 'tenant_editor') ORDER BY id");
$roleStmt->execute();
$roles = $roleStmt->fetchAll();

$tenantAdminRole = null;
foreach ($roles as $role) {
    if (in_array($role['name'], ['tenant_owner', 'tenant_admin', 'tenant_editor'], true)) {
        $tenantAdminRole = $role;
        break;
    }
}

if (!$tenantAdminRole) {
    fwrite(STDERR, "Could not find tenant owner/admin/editor role. Run role seeds.\n");
    exit(1);
}

$membershipStmt = $pdo->prepare(
    "SELECT id
     FROM tenant_memberships
     WHERE tenant_id = :tenant_id
       AND user_id = :user_id
     LIMIT 1"
);
$membershipStmt->execute([
    'tenant_id' => (int) $tenant['id'],
    'user_id' => (int) $user['id'],
]);
$membership = $membershipStmt->fetch();

if (!$membership) {
    $insert = $pdo->prepare(
        "INSERT INTO tenant_memberships (
            tenant_id,
            user_id,
            status,
            created_at,
            updated_at
        ) VALUES (
            :tenant_id,
            :user_id,
            'active',
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )"
    );

    $insert->execute([
        'tenant_id' => (int) $tenant['id'],
        'user_id' => (int) $user['id'],
    ]);

    $membershipId = (int) $pdo->lastInsertId();
} else {
    $membershipId = (int) $membership['id'];

    $pdo->prepare(
        "UPDATE tenant_memberships
         SET status = 'active',
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    )->execute(['id' => $membershipId]);
}

$roleAssignStmt = $pdo->prepare(
    "SELECT id
     FROM role_assignments
     WHERE user_id = :user_id
       AND role_id = :role_id
       AND tenant_id = :tenant_id
     LIMIT 1"
);
$roleAssignStmt->execute([
    'user_id' => (int) $user['id'],
    'role_id' => (int) $tenantAdminRole['id'],
    'tenant_id' => (int) $tenant['id'],
]);

$assignment = $roleAssignStmt->fetch();

if (!$assignment) {
    $pdo->prepare(
        "INSERT INTO role_assignments (
            user_id,
            role_id,
            tenant_id,
            created_at
        ) VALUES (
            :user_id,
            :role_id,
            :tenant_id,
            CURRENT_TIMESTAMP
        )"
    )->execute([
        'user_id' => (int) $user['id'],
        'role_id' => (int) $tenantAdminRole['id'],
        'tenant_id' => (int) $tenant['id'],
    ]);
}

$verify = $pdo->prepare(
    "SELECT
        u.id AS user_id,
        u.email,
        t.id AS tenant_id,
        t.slug AS tenant_slug,
        tm.id AS membership_id,
        tm.status AS membership_status,
        r.name AS role_name
     FROM users u
     JOIN tenant_memberships tm ON tm.user_id = u.id
     JOIN tenants t ON t.id = tm.tenant_id
     LEFT JOIN role_assignments ra ON ra.user_id = u.id AND ra.tenant_id = t.id
     LEFT JOIN roles r ON r.id = ra.role_id
     WHERE u.id = :user_id
       AND t.id = :tenant_id"
);
$verify->execute([
    'user_id' => (int) $user['id'],
    'tenant_id' => (int) $tenant['id'],
]);

echo json_encode($verify->fetchAll(), JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
