<?php

/**
 * Static regression checks for DNS expected-IP configuration, tenant reset
 * null-id hardening, and platform domain add-by-slug support.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$defensiveForgotCall = 'createResetTokenForTenantEmail($email, (int) ($tenant->tenantId ?? $tenant->id ?? 0))';
$defensiveResetCall = 'resetPasswordForTenant($token, $password, (int) ($tenant->tenantId ?? $tenant->id ?? 0))';

$checks = [
    'tenant reset accepts nullable tenant id and fails closed' => [
        'file' => $root . '/app/Platform/Auth/Password/PasswordResetService.php',
        'must' => [
            'createResetTokenForTenantEmail(string $email, ?int $tenantId): ?array',
            'resetPasswordForTenant(string $rawToken, string $newPassword, ?int $tenantId): int',
            'if ($tenantId === null || $tenantId < 1)',
            'FROM tenant_memberships',
        ],
        'must_not' => [
            'createResetTokenForTenantEmail(string $email, int $tenantId): ?array',
            'resetPasswordForTenant(string $rawToken, string $newPassword, int $tenantId): int',
        ],
    ],
    'tenant reset routes resolve tenant id defensively' => [
        'file' => $root . '/app/Http/Routes/tenant.php',
        'must' => [
            $defensiveForgotCall,
            $defensiveResetCall,
        ],
        'must_not' => [
            'createResetTokenForTenantEmail($email, $tenant->tenantId)',
            'resetPasswordForTenant($token, $password, $tenant->tenantId)',
        ],
    ],
    'dns verification expected ip is not placeholder' => [
        'file' => $root . '/app/Platform/Domains/DomainAdminService.php',
        'must' => [
            'private function expectedPublicIp(): string',
            "getenv('ARTSFOLIO_SERVER_PUBLIC_IP')",
            "return '153.75.250.37';",
        ],
        'must_not' => [
            '"expected_ipv4":["SERVER_PUBLIC_IP"]',
            "'expected_ipv4' => ['SERVER_PUBLIC_IP']",
            'expectedIpv4 = [\'SERVER_PUBLIC_IP\']',
        ],
    ],
    'platform custom domain accepts tenant slug or id' => [
        'file' => $root . '/app/Platform/Domains/DomainAdminService.php',
        'must' => [
            'public function resolveTenantId(string $tenantReference): int',
            'WHERE slug = :slug',
        ],
        'must_not' => [],
    ],
    'platform domain form accepts tenant reference' => [
        'file' => $root . '/app/Http/Controllers/Platform/Admin/DomainsController.php',
        'must' => [
            'name="tenant_ref"',
            'resolveTenantId((string) ($_POST',
            "'tenant_ref'",
            "'tenant_id'",
        ],
        'must_not' => [],
    ],
];

foreach ($checks as $label => $config) {
    $content = file_get_contents($config['file']);
    if ($content === false) {
        fwrite(STDERR, "FAILED: {$label}\nMissing file: {$config['file']}\n");
        exit(1);
    }

    foreach ($config['must'] as $needle) {
        if (!str_contains($content, $needle)) {
            fwrite(STDERR, "FAILED: {$label}\nMissing: {$needle}\n");
            exit(1);
        }
    }

    foreach ($config['must_not'] as $needle) {
        if (str_contains($content, $needle)) {
            fwrite(STDERR, "FAILED: {$label}\nForbidden stale code remains: {$needle}\n");
            exit(1);
        }
    }
}

echo "Domain DNS and tenant reset static checks passed.\n";

// End of file.
