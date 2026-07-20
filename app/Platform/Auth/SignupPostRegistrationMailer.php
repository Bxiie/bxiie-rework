<?php

declare(strict_types=1);

namespace App\Platform\Auth;

use App\Platform\Email\EmailOutboxRepository;
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
    public function queueForEmail(string $email, ?string $tenantSlug = null): array
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
        if ($tenantSlug === '') {
            $tenantSlug = (string) $this->findTenantSlug((int) $user['id']);
        }
        if ($tenantSlug === '') {
            throw new RuntimeException('No tenant membership was found for that user.');
        }

        $verification = false;
        if (!$this->isVerified($user) && !$this->hasPending($email, 'auth.email_verification_request')) {
            $verification = $this->queueVerification((int) $user['id'], $email);
        }

        $welcome = false;
        if (!$this->hasPending($email, 'lifecycle.welcome')) {
            $welcome = $this->queueWelcome($email, $tenantSlug);
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

    private function findTenantSlug(int $userId): ?string
    {
        foreach (['tenant_memberships', 'memberships', 'tenant_users'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $columns = $this->columns($table);
            if (!in_array('user_id', $columns, true) || !in_array('tenant_id', $columns, true)) {
                continue;
            }
            $statement = $this->pdo->prepare(
                "SELECT t.slug FROM `{$table}` m JOIN tenants t ON t.id = m.tenant_id WHERE m.user_id = :user_id ORDER BY t.id LIMIT 1"
            );
            $statement->execute(['user_id' => $userId]);
            $slug = $statement->fetchColumn();
            return is_string($slug) && trim($slug) !== '' ? trim($slug) : null;
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

    private function queueVerification(int $userId, string $email): bool
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->storeVerificationToken($userId, $email, $rawToken);
        $this->storeToken($userId, $rawToken);
        $url = 'https://artsfol.io' . $this->verificationPath
            . (str_contains($this->verificationPath, '?') ? '&' : '?')
            . 'token=' . rawurlencode($rawToken);
        $body = strtr($this->template('auth/email-verification-request.md'), [
            '{{ verification_url }}' => $url,
            '{{VERIFICATION_URL}}' => $url,
            '{{ recipient_email }}' => $email,
            '{{RECIPIENT_EMAIL}}' => $email,
        ]);
        $this->outbox->queue(
            $email,
            'Verify your ArtsFolio email address',
            $body,
            nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
            null,
            null,
            null,
            'auth.email_verification_request',
        );
        return true;
    }

    private function queueWelcome(string $email, string $tenantSlug): bool
    {
        $siteUrl = 'https://' . $tenantSlug . '.artsfol.io';
        $adminUrl = $siteUrl . '/admin';
        $body = strtr($this->template('lifecycle/welcome.md'), [
            '{{ recipient_email }}' => $email,
            '{{RECIPIENT_EMAIL}}' => $email,
            '{{ tenant_slug }}' => $tenantSlug,
            '{{TENANT_SLUG}}' => $tenantSlug,
            '{{ site_url }}' => $siteUrl,
            '{{SITE_URL}}' => $siteUrl,
            '{{ admin_url }}' => $adminUrl,
            '{{ADMIN_URL}}' => $adminUrl,
        ]);
        $this->outbox->queue(
            $email,
            'Welcome to ArtsFolio',
            $body,
            nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
            null,
            null,
            null,
            'lifecycle.welcome',
        );
        return true;
    }

    private function storeToken(int $userId, string $rawToken): void
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

        $this->pdo->prepare("DELETE FROM `{$table}` WHERE user_id = :user_id")->execute(['user_id' => $userId]);
        $fields = ['user_id', $tokenColumn];
        $values = [':user_id', ':token'];
        $params = ['user_id' => $userId, 'token' => $tokenColumn === 'token_hash' ? hash('sha256', $rawToken) : $rawToken];
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

    private function hasPending(string $email, string $templateKey): bool
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
            if (in_array('status', $columns, true)) {
                $where[] = "`status` IN ('pending','queued','sending')";
            }
            if (in_array('sent_at', $columns, true)) {
                $where[] = '`sent_at` IS NULL';
            }
            $statement = $this->pdo->prepare("SELECT 1 FROM `{$table}` WHERE " . implode(' AND ', $where) . ' LIMIT 1');
            $statement->execute(['email' => $email, 'template_key' => $templateKey]);
            return $statement->fetchColumn() !== false;
        }
        return false;
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

    private function storeVerificationToken(int $userId, string $email, string $rawToken): void
    {
        $candidateTables = [
            'email_verification_tokens',
            'user_email_verification_tokens',
            'user_verification_tokens',
        ];

        $table = null;
        foreach ($candidateTables as $candidate) {
            $statement = $this->pdo->prepare(
                'SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                 LIMIT 1'
            );
            $statement->execute(['table_name' => $candidate]);

            if ($statement->fetchColumn() !== false) {
                $table = $candidate;
                break;
            }
        }

        if ($table === null) {
            throw new RuntimeException('No email-verification token table exists.');
        }

        $columnStatement = $this->pdo->prepare(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
             ORDER BY ORDINAL_POSITION'
        );
        $columnStatement->execute(['table_name' => $table]);

        $columns = array_values(
            array_map('strval', $columnStatement->fetchAll(PDO::FETCH_COLUMN))
        );

        $tokenColumn = in_array('token_hash', $columns, true)
            ? 'token_hash'
            : (in_array('token', $columns, true) ? 'token' : null);

        if ($tokenColumn === null) {
            throw new RuntimeException(
                'The email-verification token table has no supported token column.'
            );
        }

        $storedToken = $tokenColumn === 'token_hash'
            ? hash('sha256', $rawToken)
            : $rawToken;

        $deleteClauses = [];
        $deleteParameters = [];

        if (in_array('user_id', $columns, true)) {
            $deleteClauses[] = 'user_id = :delete_user_id';
            $deleteParameters['delete_user_id'] = $userId;
        }

        if (in_array('email', $columns, true)) {
            $deleteClauses[] = 'LOWER(email) = :delete_email';
            $deleteParameters['delete_email'] = strtolower($email);
        }

        if ($deleteClauses !== []) {
            $delete = $this->pdo->prepare(
                'DELETE FROM `' . $table . '` WHERE '
                . implode(' OR ', $deleteClauses)
            );
            $delete->execute($deleteParameters);
        }

        $fields = [];
        $values = [];
        $parameters = [];

        if (in_array('user_id', $columns, true)) {
            $fields[] = 'user_id';
            $values[] = ':user_id';
            $parameters['user_id'] = $userId;
        }

        if (in_array('email', $columns, true)) {
            $fields[] = 'email';
            $values[] = ':email';
            $parameters['email'] = strtolower($email);
        }

        $fields[] = $tokenColumn;
        $values[] = ':token';
        $parameters['token'] = $storedToken;

        if (in_array('expires_at', $columns, true)) {
            $fields[] = 'expires_at';
            $values[] = ':expires_at';
            $parameters['expires_at'] = (new DateTimeImmutable('+24 hours'))
                ->format('Y-m-d H:i:s');
        }

        if (in_array('created_at', $columns, true)) {
            $fields[] = 'created_at';
            $values[] = 'CURRENT_TIMESTAMP';
        }

        if (in_array('updated_at', $columns, true)) {
            $fields[] = 'updated_at';
            $values[] = 'CURRENT_TIMESTAMP';
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO `' . $table . '` (`'
            . implode('`, `', $fields)
            . '`) VALUES ('
            . implode(', ', $values)
            . ')'
        );
        $statement->execute($parameters);
    }

}

// End of file.
