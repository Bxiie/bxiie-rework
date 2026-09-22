<?php

declare(strict_types=1);

namespace App\Tenant\Archive;

use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;
use PDO;
use Throwable;

/** Restores archived records without publishing them or losing their content. */
final class ArchivedItemRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function restore(TenantContext $tenant, string $kind, int $id, int $userId): bool
    {
        [$table, $status] = match ($kind) {
            'artwork' => ['artworks', 'draft'],
            'section' => ['portfolio_sections', 'hidden'],
            default => throw new \InvalidArgumentException('Unknown archive kind'),
        };
        $this->pdo->beginTransaction();
        try {
            $schedule = $kind === 'artwork' ? ', scheduled_publish_at = NULL' : '';
            $stmt = $this->pdo->prepare("UPDATE {$table} SET status = :status{$schedule}, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND tenant_id = :tenant_id AND status = 'archived'");
            $stmt->execute(['status' => $status, 'id' => $id, 'tenant_id' => $tenant->tenantId]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }
            if ($kind === 'artwork') {
                // A pending release must not publish a newly restored draft.
                $stmt = $this->pdo->prepare('DELETE FROM artwork_release_group_items WHERE tenant_id = :tenant_id AND artwork_id = :id');
                $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $id]);
            }
            (new AuditLogRepository($this->pdo))->record(
                action: 'tenant.' . $kind . '.restored', tenantId: $tenant->tenantId,
                userId: $userId, entityType: $kind, entityId: (string) $id,
                details: ['from_status' => 'archived', 'to_status' => $status],
            );
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
