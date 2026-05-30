<?php
/**
 * Resolves a tenant by mapped domain or /artist/{slug} URL.
 */

declare(strict_types=1);

namespace App\Services;

use PDO;

final class TenantResolver
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function resolve(string $host, string $path): array
    {
        $host = strtolower(preg_replace('/:\\d+$/', '', $host));
        if (preg_match('#^/artist/([^/]+)#', $path, $matches)) {
            return $this->bySlug($matches[1]);
        }

        $stmt = $this->db->prepare('SELECT t.* FROM tenants t JOIN tenant_domains d ON d.tenant_id = t.id WHERE d.domain = :domain LIMIT 1');
        $stmt->execute(['domain' => $host]);
        $tenant = $stmt->fetch();
        return $tenant ?: $this->bySlug($this->config['default_tenant_slug']);
    }

    private function bySlug(string $slug): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tenants WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            http_response_code(404);
            exit('Tenant not found.');
        }
        return $tenant;
    }
}

// End of file.
