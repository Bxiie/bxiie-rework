<?php

declare(strict_types=1);

namespace App\Tenant\Sales;

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\TemplateRenderer;
use PDO;

/**
 * Queues abandoned-cart reminder emails for known cart owners.
 *
 * The service is intentionally shared by the CLI script and background worker
 * handler so reminder eligibility, templates, and sent-marker updates cannot
 * drift between cron and queue execution paths.
 */
final class AbandonedCartEmailQueueService
{
    private const EMAIL_BRIDGE_TTL_SECONDS = 1209600;

    /** @var array<string,array{days:int,column:string,template:string,subject:string,template_key:string}> */
    private const STAGES = [
        '1d' => [
            'days' => 1,
            'column' => 'abandoned_1d_email_sent_at',
            'template' => 'abandoned-cart-1d.md',
            'subject' => 'Your ArtsFolio cart is waiting',
            'template_key' => 'sales.abandoned_cart_1d',
        ],
        '3d' => [
            'days' => 3,
            'column' => 'abandoned_3d_email_sent_at',
            'template' => 'abandoned-cart-3d.md',
            'subject' => 'A reminder about your ArtsFolio cart',
            'template_key' => 'sales.abandoned_cart_3d',
        ],
        '7d' => [
            'days' => 7,
            'column' => 'abandoned_7d_email_sent_at',
            'template' => 'abandoned-cart-7d.md',
            'subject' => 'Last reminder about your ArtsFolio cart',
            'template_key' => 'sales.abandoned_cart_7d',
        ],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $root,
        private readonly ?EmailOutboxRepository $outbox = null,
        private readonly ?TemplateRenderer $renderer = null,
    ) {
    }

    /**
     * Queue due abandoned-cart reminders.
     *
     * @return array{queued_1d:int,queued_3d:int,queued_7d:int,total:int}
     */
    public function queueDue(int $limitPerStage = 200): array
    {
        $this->assertSchemaReady();
        $outbox = $this->outbox ?? new EmailOutboxRepository($this->pdo);
        $renderer = $this->renderer ?? new TemplateRenderer();
        $limitPerStage = max(1, min(1000, $limitPerStage));
        $result = ['queued_1d' => 0, 'queued_3d' => 0, 'queued_7d' => 0, 'total' => 0];

        foreach (self::STAGES as $stage => $config) {
            foreach ($this->eligibleCarts($config, $limitPerStage) as $cart) {
                $email = strtolower(trim((string) ($cart['recipient_email'] ?? '')));
                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    continue;
                }

                $cartUrl = $this->restoreUrl($cart);
                $body = $renderer->renderFile($this->root . '/template/email/sales/' . $config['template'], [
                    'cart_url' => $cartUrl,
                    'tenant_name' => (string) ($cart['tenant_name'] ?? 'this artist'),
                    'item_count' => (string) max(1, (int) ($cart['item_count'] ?? 1)),
                    'cart_total' => $this->formatMoney((int) ($cart['cart_total_cents'] ?? 0)),
                ]);

                $this->pdo->beginTransaction();
                try {
                    if (!$this->cartStillEligible((int) $cart['id'], $config['column'])) {
                        $this->pdo->commit();
                        continue;
                    }

                    $outbox->queue(
                        recipientEmail: $email,
                        subject: $config['subject'],
                        bodyText: $body,
                        recipientName: $cart['recipient_name'] ? (string) $cart['recipient_name'] : null,
                        tenantId: (int) $cart['tenant_id'],
                        userId: isset($cart['user_id']) && $cart['user_id'] !== null ? (int) $cart['user_id'] : null,
                        templateKey: $config['template_key'],
                    );

                    $update = $this->pdo->prepare('UPDATE sales_carts SET ' . $config['column'] . ' = UTC_TIMESTAMP(), updated_at = updated_at WHERE id = :id AND ' . $config['column'] . ' IS NULL');
                    $update->execute(['id' => (int) $cart['id']]);
                    $this->pdo->commit();
                } catch (\Throwable $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }

                $result['queued_' . $stage]++;
                $result['total']++;
            }
        }

        return $result;
    }

    /** @param array{days:int,column:string,template:string,subject:string,template_key:string} $config @return list<array<string,mixed>> */
    private function eligibleCarts(array $config, int $limit): array
    {
        $days = max(1, (int) $config['days']);
        $column = $config['column'];
        $sql = '
            SELECT
                c.id,
                c.tenant_id,
                c.user_id,
                c.cart_token,
                c.customer_email,
                c.contact_email,
                c.customer_name,
                t.name AS tenant_name,
                t.slug AS tenant_slug,
                COALESCE(NULLIF(c.contact_email, ""), NULLIF(c.customer_email, ""), u.email) AS recipient_email,
                COALESCE(NULLIF(c.customer_name, ""), u.display_name) AS recipient_name,
                COALESCE(td.hostname, CONCAT(t.slug, ".artsfol.io")) AS hostname,
                COUNT(i.id) AS item_count,
                COALESCE(SUM(i.quantity * i.unit_price_cents), 0)
                  + COALESCE(SUM(i.shipping_price_cents + GREATEST(i.quantity - 1, 0) * i.shipping_additional_item_cents), 0) AS cart_total_cents
            FROM sales_carts c
            JOIN tenants t ON t.id = c.tenant_id
            LEFT JOIN users u ON u.id = c.user_id
            JOIN sales_cart_items i ON i.cart_id = c.id
            LEFT JOIN tenant_domains td ON td.id = (
                SELECT td2.id
                FROM tenant_domains td2
                WHERE td2.tenant_id = c.tenant_id
                  AND td2.status = "active"
                ORDER BY td2.is_primary DESC, FIELD(td2.domain_type, "custom", "platform_subdomain"), td2.id ASC
                LIMIT 1
            )
            WHERE c.status = "active"
              AND t.status NOT IN ("suspended", "archived")
              AND COALESCE(c.last_item_added_at, c.updated_at, c.created_at) <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)
              AND c.' . $column . ' IS NULL
              AND COALESCE(NULLIF(c.contact_email, ""), NULLIF(c.customer_email, ""), u.email) IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM sales_orders o
                  WHERE o.cart_id = c.id
                    AND o.payment_status IN ("checkout_pending", "paid", "complete", "succeeded")
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM email_signups es
                  WHERE es.tenant_id = c.tenant_id
                    AND LOWER(es.email) = LOWER(COALESCE(NULLIF(c.contact_email, ""), NULLIF(c.customer_email, ""), u.email))
                    AND es.consent_status = "unsubscribed"
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM newsletter_subscribers ns
                  WHERE ns.tenant_id = c.tenant_id
                    AND LOWER(ns.email) = LOWER(COALESCE(NULLIF(c.contact_email, ""), NULLIF(c.customer_email, ""), u.email))
                    AND ns.status IN ("unsubscribed", "bounced", "complained")
              )
              AND EXISTS (
                  SELECT 1
                  FROM sales_cart_items ci
                  JOIN artworks a ON a.id = ci.artwork_id AND a.tenant_id = c.tenant_id
                  JOIN artwork_sale_config cfg ON cfg.artwork_id = a.id AND cfg.tenant_id = a.tenant_id
                  JOIN artwork_sale_variants v ON v.id = ci.variant_id AND v.artwork_id = a.id AND v.tenant_id = a.tenant_id
                  WHERE ci.cart_id = c.id
                    AND a.status = "published"
                    AND a.sale_status = "for_sale"
                    AND cfg.checkout_enabled = 1
                    AND v.is_active = 1
                    AND ci.quantity <= GREATEST(0, v.inventory_quantity - COALESCE((
                        SELECT SUM(r.quantity)
                        FROM sales_inventory_reservations r
                        WHERE r.variant_id = v.id
                          AND r.status = "reserved"
                          AND r.expires_at > UTC_TIMESTAMP()
                    ), 0))
              )
            GROUP BY c.id, c.tenant_id, c.user_id, c.cart_token, c.customer_email, c.contact_email, c.customer_name, t.name, t.slug, u.email, u.display_name, td.hostname
            ORDER BY COALESCE(c.last_item_added_at, c.updated_at, c.created_at) ASC, c.id ASC
            LIMIT ' . $limit;
        $stmt = $this->pdo->query($sql);

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    private function cartStillEligible(int $cartId, string $sentColumn): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM sales_carts WHERE id = :id AND status = "active" AND ' . $sentColumn . ' IS NULL LIMIT 1');
        $stmt->execute(['id' => $cartId]);

        return (bool) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $cart */
    private function restoreUrl(array $cart): string
    {
        $host = $this->normalizeHost((string) ($cart['hostname'] ?? ''));
        if ($host === '') {
            $host = $this->normalizeHost((string) ($cart['tenant_slug'] ?? '') . '.artsfol.io');
        }

        $payload = [
            'tenant_id' => (int) $cart['tenant_id'],
            'cart_id' => (int) $cart['id'],
            'issued_at' => time(),
            'expires_at' => time() + self::EMAIL_BRIDGE_TTL_SECONDS,
            'source_host' => 'email',
            'target_host' => $host,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $body, $this->secret());

        return 'https://' . $host . '/cart/bridge?token=' . rawurlencode($body . '.' . $signature) . '&next=%2Fcart';
    }

    private function assertSchemaReady(): void
    {
        foreach ([
            'contact_email',
            'user_id',
            'last_item_added_at',
            'abandoned_1d_email_sent_at',
            'abandoned_3d_email_sent_at',
            'abandoned_7d_email_sent_at',
        ] as $column) {
            if (!$this->columnExists('sales_carts', $column)) {
                throw new \RuntimeException("Missing sales_carts.{$column}; run migrations before queueing abandoned-cart emails.");
            }
        }
        foreach (['artwork_sale_config', 'artwork_sale_variants', 'sales_cart_aliases'] as $table) {
            if (!$this->tableExists($table)) {
                throw new \RuntimeException("Missing {$table}; run shopping-cart migrations before queueing abandoned-cart emails.");
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function formatMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    private function secret(): string
    {
        $secret = (string) (getenv('ARTSFOLIO_CART_BRIDGE_SECRET') ?: getenv('APP_KEY') ?: getenv('ARTSFOLIO_APP_KEY') ?: '');
        if ($secret !== '') {
            return $secret;
        }

        return hash('sha256', dirname(__DIR__, 3) . '|artsfolio-cart-bridge');
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim(explode(':', $host)[0]));

        return preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

// End of file.
