<?php

/**
 * Tenant email signup persistence and admin list queries.
 */

declare(strict_types=1);

namespace App\Tenant\Signup;

use App\Platform\Tenancy\TenantContext;
use PDO;

/**
 * Persists tenant public email-list signups and admin-maintained list metadata.
 */
final class EmailSignupRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Return an existing tenant email-list signup by normalized address.
     *
     * Public signup handling uses this before queueing notifications so a
     * repeat signup by an already-active subscriber can update metadata
     * without creating another tenant-admin notification email.
     *
     * @return array<string,mixed>|null
     */
    public function findByEmail(TenantContext $tenant, string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM email_signups
             WHERE tenant_id = :tenant_id
               AND email = :email
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'email' => strtolower(trim($email)),
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function upsert(
        TenantContext $tenant,
        string $email,
        ?string $name = null,
        ?string $source = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $country = null,
        ?string $region = null,
        ?string $city = null,
        string $consentStatus = 'pending',
        ?string $notes = null,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_signups (
                tenant_id,
                email,
                name,
                source,
                notes,
                ip_address,
                user_agent,
                country,
                region,
                city,
                consent_status,
                updated_at
            ) VALUES (
                :tenant_id,
                :email,
                :name,
                :source,
                :notes,
                :ip_address,
                :user_agent,
                :country,
                :region,
                :city,
                :consent_status,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                name = COALESCE(VALUES(name), name),
                source = COALESCE(VALUES(source), source),
                notes = COALESCE(VALUES(notes), notes),
                ip_address = COALESCE(VALUES(ip_address), ip_address),
                user_agent = COALESCE(VALUES(user_agent), user_agent),
                country = COALESCE(VALUES(country), country),
                region = COALESCE(VALUES(region), region),
                city = COALESCE(VALUES(city), city),
                consent_status = CASE
                    WHEN consent_status IN ("pending", "confirmed") THEN consent_status
                    ELSE VALUES(consent_status)
                END,
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'email' => strtolower(trim($email)),
            'name' => $this->nullableTrim($name),
            'source' => $this->nullableTrim($source),
            'notes' => $this->nullableTrim($notes),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'country' => $country,
            'region' => $region,
            'city' => $city,
            'consent_status' => $consentStatus,
        ]);

        $insertId = (int) $this->pdo->lastInsertId();
        if ($insertId > 0) {
            return $insertId;
        }

        $existing = $this->findByEmail($tenant, $email);
        if ($existing !== null && isset($existing['id'])) {
            return (int) $existing['id'];
        }

        throw new \RuntimeException('Email signup was saved but its id could not be resolved.');
    }

    public function updateConsentStatus(TenantContext $tenant, int $signupId, string $status): void
    {
        $allowed = ['pending', 'confirmed', 'unsubscribed'];

        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid email signup consent status: {$status}");
        }

        $confirmedAt = $status === 'confirmed' ? 'CURRENT_TIMESTAMP' : 'confirmed_at';
        $unsubscribedAt = $status === 'unsubscribed' ? 'CURRENT_TIMESTAMP' : 'unsubscribed_at';

        $stmt = $this->pdo->prepare(
            "UPDATE email_signups
             SET consent_status = :status,
                 confirmed_at = {$confirmedAt},
                 unsubscribed_at = {$unsubscribedAt},
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id
               AND id = :id"
        );

        $stmt->execute([
            'status' => $status,
            'tenant_id' => $tenant->tenantId,
            'id' => $signupId,
        ]);
    }

    public function updateAdminFields(TenantContext $tenant, int $signupId, ?string $name, ?string $source, ?string $notes): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE email_signups
             SET name = :name,
                 source = :source,
                 notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = :tenant_id
               AND id = :id'
        );

        $stmt->execute([
            'name' => $this->nullableTrim($name),
            'source' => $this->nullableTrim($source),
            'notes' => $this->nullableTrim($notes),
            'tenant_id' => $tenant->tenantId,
            'id' => $signupId,
        ]);
    }

    public function delete(TenantContext $tenant, int $signupId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM email_signups WHERE tenant_id = :tenant_id AND id = :id');
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'id' => $signupId,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function latestForTenant(TenantContext $tenant, int $limit = 20, int $offset = 0): array
    {
        return $this->searchForTenant($tenant, '', 'created_at', 'desc', $limit, $offset);
    }

    /** @return list<array<string,mixed>> */
    public function searchForTenant(TenantContext $tenant, string $query = '', string $sort = 'created_at', string $direction = 'desc', int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->searchClause($tenant, $query);
        $order = $this->orderBy($sort, $direction);

        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM email_signups
             {$where}
             {$order}
             LIMIT :limit_count OFFSET :offset_count"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === 'tenant_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit_count', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_count', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countForTenant(TenantContext $tenant, string $query = ''): int
    {
        [$where, $params] = $this->searchClause($tenant, $query);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM email_signups {$where}");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === 'tenant_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** Records when a tenant administrator last opened the email-signup list. */
    public function markAdminViewed(TenantContext $tenant): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at, updated_at)
             VALUES (:tenant_id, 'email_signups_last_viewed_at', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE setting_value = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
    }

    /** @return array{0:string,1:array<string,int|string>} */
    private function searchClause(TenantContext $tenant, string $query): array
    {
        $where = 'WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenant->tenantId];
        $query = trim($query);

        if ($query !== '') {
            $where .= ' AND (email LIKE :query OR name LIKE :query OR source LIKE :query OR notes LIKE :query OR consent_status LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        return [$where, $params];
    }

    private function orderBy(string $sort, string $direction): string
    {
        $columns = [
            'email' => 'email',
            'name' => 'name',
            'source' => 'source',
            'consent_status' => 'consent_status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $column = $columns[$sort] ?? 'created_at';
        $dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        return "ORDER BY {$column} {$dir}, id DESC";
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}

// End of file.
