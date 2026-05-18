<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use PDO;

/**
 * Caddy on-demand TLS authorization endpoint.
 *
 * Caddy calls this before issuing a certificate for an unknown host. The
 * endpoint approves only active platform domains or active tenant_domains rows.
 */
final class CaddyAskController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function ask(Request $request): Response
    {
        $domain = strtolower(trim((string) ($_GET['domain'] ?? '')));

        if ($domain === '' || !$this->looksLikeHostname($domain)) {
            return Response::text('forbidden', 403);
        }

        if ($this->isApprovedPlatformDomain($domain) || $this->isApprovedTenantDomain($domain)) {
            return Response::text('ok');
        }

        return Response::text('forbidden', 403);
    }

    private function looksLikeHostname(string $domain): bool
    {
        if (strlen($domain) > 253) {
            return false;
        }

        if (str_contains($domain, '*') || str_contains($domain, '/')) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $domain);
    }

    private function isApprovedPlatformDomain(string $domain): bool
    {
        return in_array($domain, [
            'artsfol.io',
            'www.artsfol.io',
            'bxiie.com',
            'www.bxiie.com',
        ], true);
    }

    private function isApprovedTenantDomain(string $domain): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM tenant_domains
             WHERE hostname = :domain
               AND status = 'active'
             LIMIT 1"
        );

        $stmt->execute(['domain' => $domain]);

        return (bool) $stmt->fetchColumn();
    }
}

// End of file.
