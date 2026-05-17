<?php

declare(strict_types=1);

/**
 * Diagnoses local password login behavior for platform context versus bxiie tenant context.
 */

use App\Platform\Auth\Password\PasswordAuthService;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Identity\UserIdentityRepository;
use App\Platform\Identity\UserRepository;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$email = 'password-auth-test@example.test';
$password = 'local-test-password';

$tenant = (new TenantResolver($pdo))->resolveFromHost('bxiie.com');

$service = new PasswordAuthService(
    new UserRepository($pdo),
    new UserIdentityRepository($pdo),
    new PasswordHasher(),
    new SessionRepository($pdo),
    new SessionTokenService(),
);

$results = [
    'email' => $email,
    'tenant_resolved' => $tenant ? [
        'tenant_id' => $tenant->tenantId,
        'slug' => $tenant->slug,
        'name' => $tenant->name,
    ] : null,
    'platform_login' => null,
    'tenant_login' => null,
    'public_index_login_lines' => [],
];

foreach ([null, $tenant] as $candidateTenant) {
    $key = $candidateTenant === null ? 'platform_login' : 'tenant_login';

    try {
        $login = $service->login(
            email: $email,
            password: $password,
            tenant: $candidateTenant,
            ipAddress: '127.0.0.1',
            userAgent: 'diagnose-bxiie-login',
        );

        $results[$key] = [
            'ok' => true,
            'type' => get_debug_type($login),
            'value' => is_array($login) ? $login : (array) $login,
        ];
    } catch (Throwable $e) {
        $results[$key] = [
            'ok' => false,
            'class' => get_class($e),
            'message' => $e->getMessage(),
        ];
    }
}

$index = file($root . '/public/index.php') ?: [];
foreach ($index as $lineNo => $line) {
    if (str_contains($line, "/login") || str_contains($line, "LoginController") || str_contains($line, "artsfolio_session")) {
        $results['public_index_login_lines'][] = [
            'line' => $lineNo + 1,
            'text' => trim($line),
        ];
    }
}

echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;

// End of file.
