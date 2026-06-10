<?php

/**
 * Static checks for tenant logout and tenant-scoped password reset security.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'password auth logout uses session repository' => [
        'file' => $root . '/app/Platform/Auth/Password/PasswordAuthService.php',
        'needles' => [
            'public function logoutSessionToken(string $rawToken): void',
            '$this->sessions->revokeByHash($this->tokens->hashToken($rawToken));',
        ],
        'forbidden' => [
            '$this->pdo->prepare(',
        ],
    ],
    'tenant logout revokes server-side browser session' => [
        'file' => $root . '/app/Http/Controllers/Auth/LoginController.php',
        'needles' => [
            'public function logout(Request $request): Response',
            '$this->passwordAuth->logoutSessionToken($rawToken);',
            'SessionCookie::logoutHeaders()',
        ],
        'forbidden' => [
            'logoutToken(is_string($rawToken) ? $rawToken : null)',
            'logoutSessionToken(is_string($rawToken) ? $rawToken : null)',
        ],
    ],
    'tenant password reset requires active tenant membership' => [
        'file' => $root . '/app/Platform/Auth/Password/PasswordResetService.php',
        'needles' => [
            'public function createResetTokenForTenantEmail(string $email, ?int $tenantId): ?array',
            'public function resetPasswordForTenant(string $rawToken, string $newPassword, ?int $tenantId): int',
            'private function userBelongsToTenant(int $userId, int $tenantId): bool',
            'FROM tenant_memberships',
            "AND status = 'active'",
        ],
        'forbidden' => [],
    ],
    'tenant password routes use tenant-scoped reset APIs' => [
        'file' => $root . '/public/index.php',
        'needles' => [
            'createResetTokenForTenantEmail($email, (int) ($tenant->tenantId ?? $tenant->id ?? 0))',
            'resetPasswordForTenant($token, $password, (int) ($tenant->tenantId ?? $tenant->id ?? 0))',
        ],
        'forbidden' => [],
    ],
];

foreach ($checks as $label => $config) {
    $content = file_get_contents($config['file']);
    if ($content === false) {
        fwrite(STDERR, "FAILED: {$label}\nMissing file: {$config['file']}\n");
        exit(1);
    }

    foreach ($config['needles'] as $needle) {
        if (!str_contains($content, $needle)) {
            fwrite(STDERR, "FAILED: {$label}\nMissing: {$needle}\n");
            exit(1);
        }
    }

    foreach ($config['forbidden'] as $needle) {
        if (str_contains($content, $needle)) {
            fwrite(STDERR, "FAILED: {$label}\nForbidden stale code remains: {$needle}\n");
            exit(1);
        }
    }
}

echo "Tenant auth security static checks passed.\n";

// End of file.
