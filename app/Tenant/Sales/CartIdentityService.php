<?php

declare(strict_types=1);

namespace App\Tenant\Sales;

use App\Http\Request;
use App\Platform\Tenancy\TenantContext;
use PDO;
use RuntimeException;

/**
 * Resolves tenant carts across platform subdomains and custom domains.
 *
 * Browser cookies cannot be shared between bxiie.artsfol.io and bxiie.com.
 * This service keeps one first-party cookie per host and maps those host-local
 * tokens to a canonical tenant cart through sales_cart_aliases.
 */
final class CartIdentityService
{
    private const COOKIE_NAME = 'artsfolio_cart';
    private const COOKIE_MAX_AGE = 2592000;
    private const BRIDGE_TTL_SECONDS = 900;

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Resolve the current request to an active canonical cart.
     *
     * @return array{cart:?array<string,mixed>,token:string,set_cookie:?string}
     */
    public function resolveCartForRequest(TenantContext $tenant, Request $request, bool $create): array
    {
        $host = $this->normalizeHost($request->host());
        $token = $this->requestToken();
        if ($token === '' && !$create) {
            return ['cart' => null, 'token' => '', 'set_cookie' => null];
        }

        if ($token === '') {
            $token = bin2hex(random_bytes(32));
        }

        $cart = $this->cartByAlias($tenant, $token);
        if ($cart === null) {
            $cart = $this->cartByLegacyToken($tenant, $token);
        }
        if ($cart === null && $create) {
            $cart = $this->createCart($tenant, $token);
        }
        if ($cart !== null) {
            $this->aliasTokenToCart($tenant, (int) $cart['id'], $token, $host);
        }

        return [
            'cart' => $cart,
            'token' => $token,
            'set_cookie' => $this->cartCookie($token),
        ];
    }

    /**
     * Create a short-lived bridge token for an alternate tenant domain.
     */
    public function createBridgeToken(TenantContext $tenant, int $cartId, string $sourceHost, string $targetHost): string
    {
        $payload = [
            'tenant_id' => (int) $tenant->tenantId,
            'cart_id' => $cartId,
            'issued_at' => time(),
            'expires_at' => time() + self::BRIDGE_TTL_SECONDS,
            'source_host' => $this->normalizeHost($sourceHost),
            'target_host' => $this->normalizeHost($targetHost),
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $body, $this->secret());

        return $body . '.' . $signature;
    }

    /**
     * Attach the current host's cookie to a bridge token's canonical cart.
     *
     * @return array{cart:array<string,mixed>,token:string,set_cookie:string}
     */
    public function consumeBridgeToken(TenantContext $tenant, Request $request, string $bridgeToken): array
    {
        $payload = $this->decodeBridgeToken($bridgeToken);
        $host = $this->normalizeHost($request->host());
        if ((int) ($payload['tenant_id'] ?? 0) !== (int) $tenant->tenantId) {
            throw new RuntimeException('Bridge token tenant mismatch.');
        }
        if ($host === '' || $host !== (string) ($payload['target_host'] ?? '')) {
            throw new RuntimeException('Bridge token host mismatch.');
        }
        if (!$this->hostBelongsToTenant($tenant, $host)) {
            throw new RuntimeException('Bridge target host is not active for this tenant.');
        }

        $cart = $this->cartById($tenant, (int) ($payload['cart_id'] ?? 0));
        if ($cart === null) {
            throw new RuntimeException('Bridge cart is no longer active.');
        }

        $token = $this->requestToken();
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
        }

        $current = $this->cartByAlias($tenant, $token) ?? $this->cartByLegacyToken($tenant, $token);
        if ($current !== null && (int) $current['id'] !== (int) $cart['id']) {
            $cart = $this->mergeCarts($tenant, $current, $cart);
        }

        $this->aliasTokenToCart($tenant, (int) $cart['id'], $token, $host);

        return ['cart' => $cart, 'token' => $token, 'set_cookie' => $this->cartCookie($token)];
    }

    /**
     * Return hidden bridge pixels for every other active domain on the tenant.
     */
    public function bridgePixels(TenantContext $tenant, Request $request, int $cartId): string
    {
        $currentHost = $this->normalizeHost($request->host());
        $pixels = [];
        foreach ($this->activeTenantHosts($tenant) as $host) {
            if ($host === $currentHost) {
                continue;
            }
            $token = $this->createBridgeToken($tenant, $cartId, $currentHost, $host);
            $pixels[] = '<img src="https://' . htmlspecialchars($host, ENT_QUOTES, 'UTF-8') . '/cart/bridge-pixel?token=' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" hidden loading="eager">';
        }

        return implode('', $pixels);
    }

    public function cartCookie(string $token): string
    {
        return self::COOKIE_NAME . '=' . $token . '; Path=/; Max-Age=' . self::COOKIE_MAX_AGE . '; SameSite=Lax; Secure; HttpOnly';
    }

    public function expireCartCookie(): string
    {
        return self::COOKIE_NAME . '=; Path=/; Max-Age=0; SameSite=Lax; Secure; HttpOnly';
    }

    /** @return array<string,mixed>|null */
    private function cartByAlias(TenantContext $tenant, string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*
             FROM sales_cart_aliases a
             JOIN sales_carts c ON c.id = a.cart_id
             WHERE a.tenant_id = :tenant_id
               AND a.cart_token_hash = :hash
               AND c.status = "active"
               AND c.tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'hash' => $this->tokenHash($token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function cartByLegacyToken(TenantContext $tenant, string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_carts WHERE tenant_id = :tenant_id AND cart_token = :token AND status = "active" LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function cartById(TenantContext $tenant, int $cartId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_carts WHERE tenant_id = :tenant_id AND id = :id AND status = "active" LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'id' => $cartId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string,mixed> */
    private function createCart(TenantContext $tenant, string $token): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO sales_carts (tenant_id, cart_token, status) VALUES (:tenant_id, :token, "active")');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'token' => $token]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'tenant_id' => $tenant->tenantId, 'cart_token' => $token, 'status' => 'active'];
    }

    private function aliasTokenToCart(TenantContext $tenant, int $cartId, string $token, string $host): void
    {
        if ($token === '' || $host === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales_cart_aliases (tenant_id, cart_id, cart_token_hash, domain_host, first_seen_at, last_seen_at, created_at)
             VALUES (:tenant_id, :cart_id, :hash, :host, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE cart_id = VALUES(cart_id), domain_host = VALUES(domain_host), last_seen_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'tenant_id' => $tenant->tenantId,
            'cart_id' => $cartId,
            'hash' => $this->tokenHash($token),
            'host' => $host,
        ]);
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target @return array<string,mixed> */
    private function mergeCarts(TenantContext $tenant, array $source, array $target): array
    {
        $canonical = strtotime((string) ($source['updated_at'] ?? '')) > strtotime((string) ($target['updated_at'] ?? '')) ? $source : $target;
        $other = (int) $canonical['id'] === (int) $source['id'] ? $target : $source;

        $this->pdo->beginTransaction();
        try {
            $items = $this->pdo->prepare('SELECT * FROM sales_cart_items WHERE cart_id = :cart_id ORDER BY id ASC');
            $items->execute(['cart_id' => (int) $other['id']]);
            foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO sales_cart_items (cart_id, artwork_id, variant_id, quantity, unit_price_cents, shipping_price_cents, shipping_additional_item_cents, title_snapshot, variant_label_snapshot, size_value_snapshot, gender_value_snapshot, media_uuid_snapshot)
                     VALUES (:cart_id, :artwork_id, :variant_id, :quantity, :unit_price_cents, :shipping_price_cents, :shipping_additional_item_cents, :title_snapshot, :variant_label_snapshot, :size_value_snapshot, :gender_value_snapshot, :media_uuid_snapshot)
                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = CURRENT_TIMESTAMP'
                );
                $insert->execute([
                    'cart_id' => (int) $canonical['id'],
                    'artwork_id' => (int) $item['artwork_id'],
                    'variant_id' => (int) $item['variant_id'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'unit_price_cents' => (int) $item['unit_price_cents'],
                    'shipping_price_cents' => (int) ($item['shipping_price_cents'] ?? 0),
                    'shipping_additional_item_cents' => (int) ($item['shipping_additional_item_cents'] ?? 0),
                    'title_snapshot' => (string) $item['title_snapshot'],
                    'variant_label_snapshot' => $item['variant_label_snapshot'] ?? null,
                    'size_value_snapshot' => $item['size_value_snapshot'] ?? null,
                    'gender_value_snapshot' => $item['gender_value_snapshot'] ?? null,
                    'media_uuid_snapshot' => $item['media_uuid_snapshot'] ?? null,
                ]);
            }
            $update = $this->pdo->prepare('UPDATE sales_carts SET status = "merged", merged_into_cart_id = :canonical, updated_at = CURRENT_TIMESTAMP WHERE id = :other AND tenant_id = :tenant_id');
            $update->execute(['canonical' => (int) $canonical['id'], 'other' => (int) $other['id'], 'tenant_id' => $tenant->tenantId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $canonical;
    }

    /** @return list<string> */
    private function activeTenantHosts(TenantContext $tenant): array
    {
        $stmt = $this->pdo->prepare('SELECT hostname FROM tenant_domains WHERE tenant_id = :tenant_id AND status = "active" ORDER BY is_primary DESC, domain_type ASC, id ASC');
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $hosts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $host) {
            $host = $this->normalizeHost((string) $host);
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    private function hostBelongsToTenant(TenantContext $tenant, string $host): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenant_domains WHERE tenant_id = :tenant_id AND hostname = :host AND status = "active" LIMIT 1');
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'host' => $host]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return array<string,mixed> */
    private function decodeBridgeToken(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException('Invalid bridge token.');
        }
        $expected = hash_hmac('sha256', $parts[0], $this->secret());
        if (!hash_equals($expected, $parts[1])) {
            throw new RuntimeException('Invalid bridge signature.');
        }
        $json = $this->base64UrlDecode($parts[0]);
        $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
            throw new RuntimeException('Bridge token expired.');
        }

        return $payload;
    }

    private function requestToken(): string
    {
        $token = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? $token : '';
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, $this->secret());
    }

    private function secret(): string
    {
        $secret = (string) (getenv('ARTSFOLIO_CART_BRIDGE_SECRET') ?: getenv('APP_KEY') ?: getenv('ARTSFOLIO_APP_KEY') ?: '');
        if ($secret !== '') {
            return $secret;
        }

        return hash('sha256', __DIR__ . '|artsfolio-cart-bridge');
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

    private function base64UrlDecode(string $value): string
    {
        $padded = $value . str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid bridge payload.');
        }

        return $decoded;
    }
}

// End of file.
