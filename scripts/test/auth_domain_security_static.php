<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'logout uses repository instead of null pdo' => [
        'file' => $root . '/app/Platform/Auth/Password/PasswordAuthService.php',
        'must' => [
            'public function logoutSessionToken(string $rawToken): void',
            '$this->sessions->revokeByHash($this->tokens->hashToken($rawToken));',
        ],
        'must_not' => [
            '$this->pdo->prepare(',
        ],
    ],
    'tenant reset routes are tenant scoped' => [
        'file' => $root . '/public/index.php',
        'must' => [
            'createResetTokenForTenantEmail($email, (int) ($tenant->tenantId ?? $tenant->id ?? 0))',
            'resetPasswordForTenant($token, $password, (int) ($tenant->tenantId ?? $tenant->id ?? 0))',
        ],
        'must_not' => [],
    ],
    'tenant reset service requires membership' => [
        'file' => $root . '/app/Platform/Auth/Password/PasswordResetService.php',
        'must' => [
            'createResetTokenForTenantEmail(string $email, ?int $tenantId): ?array',
            'if ($tenantId === null || $tenantId < 1)',
            'resetPasswordForTenant(string $rawToken, string $newPassword, ?int $tenantId): int',
            'FROM tenant_memberships',
            "AND status = 'active'",
        ],
        'must_not' => [],
    ],
    'domain management supports add delete and plan counting' => [
        'file' => $root . '/app/Platform/Domains/DomainAdminService.php',
        'must' => [
            'addCustomDomain(int $tenantId, string $hostname, bool $skipPlanCheck = false): int',
            'deleteDomain(int $domainId): void',
            'planAllowsDomain(int $tenantId, string $hostname): bool',
            "hostname NOT LIKE '%.artsfol.io'",
        ],
        'must_not' => [],
    ],
    'tenant deletion removes domain rows' => [
        'file' => $root . '/app/Platform/Tenants/TenantAdminRepository.php',
        'must' => [
            'DELETE FROM tenant_domains WHERE tenant_id = :tenant_id',
        ],
        'must_not' => [],
    ],
    'password reset email includes requested email address' => [
        'file' => $root . '/template/auth/password-reset-request.md',
        'must' => [
            '{{ recipient_email }}',
        ],
        'must_not' => [],
    ],
];

foreach ($checks as $label => $config) {
    $content = file_get_contents($config['file']);
    if ($content === false) {
        fwrite(STDERR, "FAILED: {$label}\nMissing file {$config['file']}\n");
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
            fwrite(STDERR, "FAILED: {$label}\nForbidden stale code: {$needle}\n");
            exit(1);
        }
    }
}

echo "Auth and domain security static checks passed.\n";

// End of file.
