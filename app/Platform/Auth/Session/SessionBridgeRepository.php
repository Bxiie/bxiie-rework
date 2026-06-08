<?php

/**
 * One-time browser session bridge ticket persistence.
 */

declare(strict_types=1);

namespace App\Platform\Auth\Session;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Stores short-lived one-time tickets used to transfer an authenticated tenant
 * admin session from a tenant artsfol.io subdomain to a verified custom domain.
 */
final class SessionBridgeRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function createTicket(string $ticketHash, int $tenantId, int $userId, string $returnUrl, int $ttlSeconds = 90): void
    {
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->add(new DateInterval('PT' . max(15, $ttlSeconds) . 'S'))
            ->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_session_bridge_tickets (
                ticket_hash,
                tenant_id,
                user_id,
                return_url,
                expires_at
            ) VALUES (
                :ticket_hash,
                :tenant_id,
                :user_id,
                :return_url,
                :expires_at
            )'
        );

        $stmt->execute([
            'ticket_hash' => $ticketHash,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'return_url' => $returnUrl,
            'expires_at' => $expiresAt,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function consumeTicket(string $ticketHash): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $select = $this->pdo->prepare(
                'SELECT *
                 FROM tenant_session_bridge_tickets
                 WHERE ticket_hash = :ticket_hash
                   AND consumed_at IS NULL
                   AND expires_at > CURRENT_TIMESTAMP
                 LIMIT 1
                 FOR UPDATE'
            );
            $select->execute(['ticket_hash' => $ticketHash]);
            $ticket = $select->fetch();

            if (!$ticket) {
                $this->pdo->commit();
                return null;
            }

            $update = $this->pdo->prepare(
                'UPDATE tenant_session_bridge_tickets
                 SET consumed_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $update->execute(['id' => (int) $ticket['id']]);
            $this->pdo->commit();

            return $ticket;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function tenantOwnsHost(int $tenantId, string $host): bool
    {
        $normalizedHost = strtolower(trim(explode(':', $host, 2)[0]));
        if ($normalizedHost === '') {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM tenant_domains
             WHERE tenant_id = :tenant_id
               AND LOWER(domain) = :domain
               AND status IN (\'active\', \'verified\', \'primary\')'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'domain' => $normalizedHost,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

// End of file.
