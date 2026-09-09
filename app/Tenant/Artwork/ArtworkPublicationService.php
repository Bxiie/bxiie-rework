<?php

declare(strict_types=1);

namespace App\Tenant\Artwork;

use App\Platform\Directory\TenantDirectoryProfileRepository;
use PDO;

final class ArtworkPublicationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{artworks:int,groups:int} */
    public function publishDue(): array
    {
        $this->pdo->beginTransaction();
        try {
            $tenantIds = $this->pdo->query("SELECT DISTINCT tenant_id FROM artworks WHERE status = 'draft' AND scheduled_publish_at IS NOT NULL AND scheduled_publish_at <= UTC_TIMESTAMP() FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $artworks = $this->pdo->exec("UPDATE artworks SET status = 'published', scheduled_publish_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE status = 'draft' AND scheduled_publish_at IS NOT NULL AND scheduled_publish_at <= UTC_TIMESTAMP()");
            $groups = $this->pdo->query("SELECT id, tenant_id FROM artwork_release_groups WHERE status = 'scheduled' AND scheduled_at <= UTC_TIMESTAMP() FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($groups as $group) {
                $tenantIds[] = (int) $group['tenant_id'];
                $publish = $this->pdo->prepare("UPDATE artworks a JOIN artwork_release_group_items i ON i.artwork_id = a.id AND i.tenant_id = a.tenant_id SET a.status = 'published', a.scheduled_publish_at = NULL, a.updated_at = CURRENT_TIMESTAMP WHERE i.release_group_id = :group_id AND a.tenant_id = :tenant_id AND a.status <> 'archived'");
                $publish->execute(['group_id' => (int) $group['id'], 'tenant_id' => (int) $group['tenant_id']]);
                $complete = $this->pdo->prepare("UPDATE artwork_release_groups SET status = 'deployed', deployed_at = UTC_TIMESTAMP(), updated_at = CURRENT_TIMESTAMP WHERE id = :id AND tenant_id = :tenant_id AND status = 'scheduled'");
                $complete->execute(['id' => (int) $group['id'], 'tenant_id' => (int) $group['tenant_id']]);
            }
            $this->pdo->commit();
            $directory = new TenantDirectoryProfileRepository($this->pdo);
            foreach (array_unique(array_map('intval', $tenantIds)) as $tenantId) {
                if ($tenantId > 0) $directory->syncTenant($tenantId);
            }
            return ['artworks' => (int) $artworks, 'groups' => count($groups)];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
