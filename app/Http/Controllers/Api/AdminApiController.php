<?php

/**
 * OAuth2-protected administrative API for platform and tenant operations.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Auth\Password\PasswordAuthService;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Membership\Roles;
use App\Platform\Signup\TenantSignupService;
use PDO;
use Throwable;

final class AdminApiController
{
    public function __construct(private readonly PDO $pdo, private readonly ?array $token)
    {
    }

    public function tenants(Request $request): Response
    {
        if (!$this->authorized('platform:write')) { return $this->unauthorized(); }
        if ($request->method() === 'GET') {
            $rows = $this->pdo->query('SELECT id, uuid, slug, name, status, created_at, suspended_at, deleted_at FROM tenants ORDER BY id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
            return $this->json(['tenants' => $rows]);
        }
        $body = $this->body();
        foreach (['slug', 'name', 'admin_email', 'admin_name', 'password'] as $required) {
            if (trim((string) ($body[$required] ?? '')) === '') { return $this->json(['error' => 'missing_' . $required], 422); }
        }
        try {
            $service = new TenantSignupService($this->pdo);
            $created = $service->createTenantFromPasswordSignup(
                slug: (string) $body['slug'],
                tenantName: (string) $body['name'],
                adminEmail: (string) $body['admin_email'],
                adminName: (string) $body['admin_name'],
                passwordHash: (new \App\Platform\Identity\PasswordHasher())->hash((string) $body['password']),
            );
            return $this->json(['tenant' => $created], 201);
        } catch (Throwable $e) {
            return $this->json(['error' => 'tenant_create_failed', 'message' => $e->getMessage()], 422);
        }
    }

    public function tenant(Request $request, int $tenantId): Response
    {
        if (!$this->authorized('platform:write')) { return $this->unauthorized(); }
        $body = $this->body();
        if ($request->method() === 'GET') {
            return $this->json(['tenant' => $this->tenantRow($tenantId)]);
        }
        $fields = [];
        $params = ['id' => $tenantId];
        foreach (['name', 'status'] as $field) {
            if (array_key_exists($field, $body)) { $fields[] = "$field = :$field"; $params[$field] = (string) $body[$field]; }
        }
        if (!$fields) { return $this->json(['error' => 'no_mutable_fields'], 422); }
        $this->pdo->prepare('UPDATE tenants SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        return $this->json(['tenant' => $this->tenantRow($tenantId)]);
    }

    public function tenantSettings(Request $request, int $tenantId): Response
    {
        if (!$this->tenantAuthorized('tenant:write', $tenantId)) { return $this->unauthorized(); }
        if ($request->method() === 'GET') {
            $stmt = $this->pdo->prepare('SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = :tenant_id ORDER BY setting_key');
            $stmt->execute(['tenant_id' => $tenantId]);
            return $this->json(['settings' => $stmt->fetchAll(PDO::FETCH_KEY_PAIR)]);
        }
        $body = $this->body();
        foreach (($body['settings'] ?? []) as $key => $value) {
            $stmt = $this->pdo->prepare('INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at) VALUES (:tenant_id, :setting_key, :setting_value, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
            $stmt->execute(['tenant_id' => $tenantId, 'setting_key' => (string) $key, 'setting_value' => (string) $value]);
        }
        return $this->json(['ok' => true]);
    }

    public function collection(Request $request, int $tenantId, string $entity): Response
    {
        if (!$this->tenantAuthorized('tenant:write', $tenantId)) { return $this->unauthorized(); }
        $map = $this->entityMap();
        if (!isset($map[$entity])) { return $this->json(['error' => 'unknown_entity'], 404); }
        $table = $map[$entity]['table'];
        if ($request->method() === 'GET') {
            $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 500");
            $stmt->execute(['tenant_id' => $tenantId]);
            return $this->json([$entity => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        $body = $this->body();
        $allowed = $map[$entity]['fields'];
        $fields = array_values(array_intersect(array_keys($body), $allowed));
        if (!$fields) { return $this->json(['error' => 'no_fields'], 422); }
        $columns = array_merge(['tenant_id'], $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (:" . implode(', :', $columns) . ")";
        $params = ['tenant_id' => $tenantId];
        foreach ($fields as $field) { $params[$field] = $body[$field]; }
        $this->pdo->prepare($sql)->execute($params);
        return $this->json(['id' => (int) $this->pdo->lastInsertId()], 201);
    }

    public function item(Request $request, int $tenantId, string $entity, int $id): Response
    {
        if (!$this->tenantAuthorized('tenant:write', $tenantId)) { return $this->unauthorized(); }
        $map = $this->entityMap();
        if (!isset($map[$entity])) { return $this->json(['error' => 'unknown_entity'], 404); }
        $table = $map[$entity]['table'];
        if ($request->method() === 'DELETE') {
            $this->pdo->prepare("DELETE FROM {$table} WHERE tenant_id = :tenant_id AND id = :id")->execute(['tenant_id' => $tenantId, 'id' => $id]);
            return $this->json(['ok' => true]);
        }
        $body = $this->body();
        $fields = array_values(array_intersect(array_keys($body), $map[$entity]['fields']));
        if (!$fields) { return $this->json(['error' => 'no_fields'], 422); }
        $assignments = array_map(static fn (string $field): string => "$field = :$field", $fields);
        $params = ['tenant_id' => $tenantId, 'id' => $id];
        foreach ($fields as $field) { $params[$field] = $body[$field]; }
        $this->pdo->prepare("UPDATE {$table} SET " . implode(', ', $assignments) . " WHERE tenant_id = :tenant_id AND id = :id")->execute($params);
        return $this->json(['ok' => true]);
    }

    private function authorized(string $scope): bool
    {
        if (!$this->token) { return false; }
        $scopes = json_decode((string) ($this->token['scopes'] ?? '[]'), true) ?: [];
        return in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }

    /**
     * Requires both the requested tenant scope and an exact token tenant match.
     * Platform-scoped tokens must use platform API routes instead of borrowing a
     * tenant URL identifier.
     */
    private function tenantAuthorized(string $scope, int $tenantId): bool
    {
        if (!$this->authorized($scope)) {
            return false;
        }

        $tokenTenantId = (int) ($this->token['tenant_id'] ?? 0);

        return $tokenTenantId > 0 && $tokenTenantId === $tenantId;
    }

    private function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function tenantRow(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, uuid, slug, name, status, created_at, suspended_at, deleted_at FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function entityMap(): array
    {
        return [
            'artworks' => ['table' => 'artworks', 'fields' => ['title', 'slug', 'medium', 'description', 'display_date', 'status', 'sort_order', 'price', 'dimensions']],
            'events' => ['table' => 'events', 'fields' => ['event_date', 'title', 'event_type', 'location', 'description', 'sort_order']],
            'portfolio-sections' => ['table' => 'portfolio_sections', 'fields' => ['name', 'slug', 'description', 'sort_order', 'is_visible']],
            'contact-messages' => ['table' => 'contact_messages', 'fields' => ['status', 'notes']],
            'email-signups' => ['table' => 'email_signups', 'fields' => ['email', 'status', 'consent_status']],
        ];
    }

    private function unauthorized(): Response { return $this->json(['error' => 'oauth2_bearer_token_required'], 401); }

    private function json(array $payload, int $status = 200): Response
    {
        return new Response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}', $status, ['Content-Type' => 'application/json']);
    }
}

// End of file.
