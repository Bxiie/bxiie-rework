<?php

declare(strict_types=1);

namespace App\Platform\Auth;

use App\Platform\Email\BrandedEmail;
use App\Platform\Email\EditableEmailTemplate;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\TemplateRenderer;
use DateTimeImmutable;
use PDO;
use RuntimeException;

/** Queues verification and welcome messages after tenant registration. */
final class SignupPostRegistrationMailer
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly EmailOutboxRepository $outbox,
        private readonly string $verificationPath = '/verify-email',
    ) {
    }

    /** @return array{verification:bool,welcome:bool} */
    public function queueForEmail(string $email, ?string $tenantSlug = null, bool $replaceOtherTenantPending = false): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.');
        }

        $user = $this->findUser($email);
        if ($user === null) {
            throw new RuntimeException('No ArtsFolio user exists for that email address.');
        }

        $tenantSlug = trim((string) $tenantSlug);
        $tenantWasExplicit = $tenantSlug !== '';
        $requestedTenantSlug = $tenantSlug;
        $tenant = $this->findTenantContext((int) $user['id'], $tenantWasExplicit ? $tenantSlug : null);
        $tenantSlug = trim((string) ($tenant['slug'] ?? $tenantSlug));
        if ($tenantSlug === '') {
            if ($tenantWasExplicit) {
                throw new RuntimeException('That user is not a member of tenant: ' . $requestedTenantSlug);
            }
            throw new RuntimeException('No tenant membership was found for that user.');
        }
        $tenantId = (int) ($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            throw new RuntimeException('The selected tenant could not be resolved.');
        }

        if ($tenantWasExplicit && $replaceOtherTenantPending) {
            $this->cancelPendingForOtherTenants($email, (int) $user['id'], $tenantId);
        }

        $verification = false;
        if (!$this->isVerified($user) && !$this->hasPending($email, 'auth.email_verification_request', $tenantId)) {
            $verification = $this->queueVerification($user, $email, $tenant);
        }

        $welcome = false;
        if (!$this->hasPending($email, 'lifecycle.welcome', $tenantId)) {
            $welcome = $this->queueWelcome($user, $email, $tenant);
        }

        return ['verification' => $verification, 'welcome' => $welcome];
    }

    /** @return array<string,mixed>|null */
    private function findUser(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(email) = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array{id:int,slug:string,name:string}|null */
    private function findTenantContext(int $userId, ?string $preferredSlug = null): ?array
    {
        foreach (['tenant_memberships', 'memberships', 'tenant_users'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $columns = $this->columns($table);
            if (!in_array('user_id', $columns, true) || !in_array('tenant_id', $columns, true)) {
                continue;
            }
            $sql = "SELECT t.id, t.slug, t.name FROM `{$table}` m JOIN tenants t ON t.id = m.tenant_id WHERE m.user_id = :user_id";
            $params = ['user_id' => $userId];
            if ($preferredSlug !== null && trim($preferredSlug) !== '') {
                $sql .= ' AND t.slug = :tenant_slug';
                $params['tenant_slug'] = trim($preferredSlug);
            }
            $sql .= ' ORDER BY t.id LIMIT 1';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'slug' => trim((string) ($row['slug'] ?? '')),
                    'name' => trim((string) ($row['name'] ?? '')),
                ];
            }
        }
        return null;
    }

    /** @param array<string,mixed> $user */
    private function isVerified(array $user): bool
    {
        foreach (['email_verified_at', 'verified_at'] as $column) {
            if (array_key_exists($column, $user)) {
                return trim((string) ($user[$column] ?? '')) !== '';
            }
        }
        return array_key_exists('email_verified', $user) && (int) $user['email_verified'] === 1;
    }

    /** @param array<string,mixed> $user @param array{id:int,slug:string,name:string}|null $tenant */
    private function queueVerification(array $user, string $email, ?array $tenant): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $rawToken = bin2hex(random_bytes(32));
        $this->storeToken($userId, (int) ($tenant['id'] ?? 0), $email, $rawToken);
        $url = 'https://artsfol.io' . $this->verificationPath
            . (str_contains($this->verificationPath, '?') ? '&' : '?')
            . 'token=' . rawurlencode($rawToken);

        $values = $this->templateValues($user, $email, $tenant, [
            'verification_url' => $url,
        ]);
        $message = $this->editableTemplate()->render('auth/email-verification-request.md', $values);
        $this->assertNoUnresolvedTokens($message['body'], 'auth/email-verification-request.md');
        $subject = $message['subject'] !== 'ArtsFolio' ? $message['subject'] : 'Verify your ArtsFolio email address';
        $bodies = BrandedEmail::render($subject, $message['body']);

        $this->outbox->queue(
            recipientEmail: $email,
            subject: $subject,
            bodyText: $bodies['body_text'],
            bodyHtml: $bodies['body_html'],
            recipientName: $values['recipient_name'],
            tenantId: ($tenant['id'] ?? 0) > 0 ? (int) $tenant['id'] : null,
            userId: $userId,
            templateKey: 'auth.email_verification_request',
        );
        return true;
    }

    /** @param array<string,mixed> $user @param array{id:int,slug:string,name:string}|null $tenant */
    private function queueWelcome(array $user, string $email, ?array $tenant): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $tenantSlug = trim((string) ($tenant['slug'] ?? ''));
        $siteUrl = 'https://' . $tenantSlug . '.artsfol.io';
        $adminUrl = $siteUrl . '/admin';
        $values = $this->templateValues($user, $email, $tenant, [
            'site_url' => $siteUrl,
            'admin_url' => $adminUrl,
        ]);
        $message = $this->editableTemplate()->render('lifecycle/welcome.md', $values);
        $this->assertNoUnresolvedTokens($message['body'], 'lifecycle/welcome.md');
        $subject = $message['subject'] !== 'ArtsFolio' ? $message['subject'] : 'Welcome to ArtsFolio';
        $bodies = BrandedEmail::render($subject, $message['body']);

        $this->outbox->queue(
            recipientEmail: $email,
            subject: $subject,
            bodyText: $bodies['body_text'],
            bodyHtml: $bodies['body_html'],
            recipientName: $values['recipient_name'],
            tenantId: ($tenant['id'] ?? 0) > 0 ? (int) $tenant['id'] : null,
            userId: $userId,
            templateKey: 'lifecycle.welcome',
        );
        return true;
    }

    /** @param array<string,mixed> $user @param array{id:int,slug:string,name:string}|null $tenant @param array<string,string> $extra @return array<string,string> */
    private function templateValues(array $user, string $email, ?array $tenant, array $extra = []): array
    {
        $recipientName = '';
        foreach (['display_name', 'name', 'full_name', 'username'] as $field) {
            $candidate = trim((string) ($user[$field] ?? ''));
            if ($candidate !== '') {
                $recipientName = $candidate;
                break;
            }
        }
        if ($recipientName === '') {
            $recipientName = strstr($email, '@', true) ?: 'there';
        }

        $tenantSlug = trim((string) ($tenant['slug'] ?? ''));
        $tenantName = trim((string) ($tenant['name'] ?? ''));
        if ($tenantName === '') {
            $tenantName = $tenantSlug !== '' ? $tenantSlug : 'your ArtsFolio site';
        }

        $platformUrl = rtrim((string) (getenv('ARTSFOLIO_PUBLIC_URL') ?: 'https://artsfol.io'), '/');
        $siteUrl = $tenantSlug !== '' ? 'https://' . $tenantSlug . '.artsfol.io' : $platformUrl;
        $adminUrl = $siteUrl . ($tenantSlug !== '' ? '/admin' : '');

        return array_merge([
            'recipient_name' => $recipientName,
            'recipient_email' => $email,
            'tenant_name' => $tenantName,
            'tenant_slug' => $tenantSlug,
            'site_url' => $siteUrl,
            'admin_url' => $adminUrl,
            'tenant_admin_url' => $adminUrl,
            'tour_url' => $siteUrl . '/admin/getting-started',
            'help_url' => $platformUrl . '/help',
            'functions_url' => $platformUrl . '/help/tenant-admin-functions',
            'videos_url' => $platformUrl . '/help/training-videos',
        ], $extra);
    }

    private function assertNoUnresolvedTokens(string $body, string $templatePath): void
    {
        if (preg_match_all('/\{\{\s*[A-Za-z0-9_.-]+\s*\}\}/', $body, $matches) < 1) {
            return;
        }

        $tokens = array_values(array_unique(array_map('trim', $matches[0] ?? [])));
        throw new RuntimeException(
            'Refusing to queue email with unresolved template tokens in '
            . $templatePath . ': ' . implode(', ', $tokens)
        );
    }

    private function editableTemplate(): EditableEmailTemplate
    {
        return new EditableEmailTemplate(
            new TemplateRenderer(),
            dirname(__DIR__, 3) . '/template/email',
        );
    }

    private function storeToken(int $userId, int $tenantId, string $email, string $rawToken): void
    {
        $table = null;
        foreach (['email_verification_tokens', 'user_email_verification_tokens', 'user_verification_tokens'] as $candidate) {
            if ($this->tableExists($candidate)) {
                $table = $candidate;
                break;
            }
        }
        if ($table === null) {
            throw new RuntimeException('No email-verification token table exists.');
        }

        $columns = $this->columns($table);
        $tokenColumn = in_array('token_hash', $columns, true) ? 'token_hash' : (in_array('token', $columns, true) ? 'token' : null);
        if ($tokenColumn === null || !in_array('user_id', $columns, true)) {
            throw new RuntimeException('The email-verification token table has an unsupported shape.');
        }

        $deleteWhere = ['user_id = :user_id'];
        $deleteParams = ['user_id' => $userId];
        if (in_array('tenant_id', $columns, true)) {
            if ($tenantId <= 0) {
                throw new RuntimeException('A tenant is required for an email-verification token.');
            }
            $deleteWhere[] = 'tenant_id = :tenant_id';
            $deleteParams['tenant_id'] = $tenantId;
        }
        $this->pdo->prepare("DELETE FROM `{$table}` WHERE " . implode(' AND ', $deleteWhere))->execute($deleteParams);

        $fields = ['user_id', $tokenColumn];
        $values = [':user_id', ':token'];
        $params = ['user_id' => $userId, 'token' => $tokenColumn === 'token_hash' ? hash('sha256', $rawToken) : $rawToken];

        if (in_array('tenant_id', $columns, true)) {
            $fields[] = 'tenant_id';
            $values[] = ':tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if (in_array('email', $columns, true)) {
            $fields[] = 'email';
            $values[] = ':email';
            $params['email'] = strtolower($email);
        }
        if (in_array('expires_at', $columns, true)) {
            $fields[] = 'expires_at';
            $values[] = ':expires_at';
            $params['expires_at'] = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
        }
        if (in_array('created_at', $columns, true)) {
            $fields[] = 'created_at';
            $values[] = 'CURRENT_TIMESTAMP';
        }
        if (in_array('updated_at', $columns, true)) {
            $fields[] = 'updated_at';
            $values[] = 'CURRENT_TIMESTAMP';
        }
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $fields) . '`) VALUES (' . implode(', ', $values) . ')';
        $this->pdo->prepare($sql)->execute($params);
    }

    private function hasPending(string $email, string $templateKey, int $tenantId): bool
    {
        foreach (['email_outbox', 'email_outbox_messages'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $columns = $this->columns($table);
            $emailColumn = $this->firstColumn($columns, ['recipient_email', 'to_email', 'email', 'recipient']);
            $keyColumn = $this->firstColumn($columns, ['template_key', 'message_type', 'category']);
            if ($emailColumn === null || $keyColumn === null) {
                return false;
            }
            $where = ["LOWER(`{$emailColumn}`) = :email", "`{$keyColumn}` = :template_key"];
            $params = ['email' => $email, 'template_key' => $templateKey];
            if (in_array('tenant_id', $columns, true)) {
                $where[] = '`tenant_id` = :tenant_id';
                $params['tenant_id'] = $tenantId;
            }
            if (in_array('status', $columns, true)) {
                $where[] = "`status` IN ('pending','queued','sending')";
            }
            if (in_array('sent_at', $columns, true)) {
                $where[] = '`sent_at` IS NULL';
            }
            $statement = $this->pdo->prepare("SELECT 1 FROM `{$table}` WHERE " . implode(' AND ', $where) . ' LIMIT 1');
            $statement->execute($params);
            return $statement->fetchColumn() !== false;
        }
        return false;
    }

    private function cancelPendingForOtherTenants(string $email, int $userId, int $tenantId): void
    {
        foreach (['email_outbox', 'email_outbox_messages'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $columns = $this->columns($table);
            $emailColumn = $this->firstColumn($columns, ['recipient_email', 'to_email', 'email', 'recipient']);
            $keyColumn = $this->firstColumn($columns, ['template_key', 'message_type', 'category']);
            if ($emailColumn === null || $keyColumn === null || !in_array('tenant_id', $columns, true) || !in_array('status', $columns, true)) {
                continue;
            }
            $where = [
                "LOWER(`{$emailColumn}`) = :email",
                "`{$keyColumn}` IN ('auth.email_verification_request','lifecycle.welcome')",
                '`tenant_id` <> :tenant_id',
                "`status` IN ('pending','queued')",
            ];
            $params = ['email' => $email, 'tenant_id' => $tenantId];
            if (in_array('user_id', $columns, true)) {
                $where[] = '`user_id` = :user_id';
                $params['user_id'] = $userId;
            }
            $sets = ["`status` = 'cancelled'"];
            if (in_array('updated_at', $columns, true)) {
                $sets[] = '`updated_at` = UTC_TIMESTAMP()';
            }
            $statement = $this->pdo->prepare(
                "UPDATE `{$table}` SET " . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $where)
            );
            $statement->execute($params);
        }
    }

    private function template(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/template/email/' . $relativePath;
        $body = is_file($path) ? (string) file_get_contents($path) : '';
        if (trim($body) === '') {
            throw new RuntimeException('Required email template is unavailable: ' . $relativePath);
        }
        return $body;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1'
        );
        $statement->execute(['table_name' => $table]);
        return $statement->fetchColumn() !== false;
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION'
        );
        $statement->execute(['table_name' => $table]);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @param list<string> $columns @param list<string> $candidates */
    private function firstColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return null;
    }

}

// End of file.
