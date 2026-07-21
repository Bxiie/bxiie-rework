<?php

declare(strict_types=1);

namespace App\Platform\Email;

use App\Platform\Settings\PlatformSettingsRepository;
use PDO;

/** Queues the next copy of a recurring signup lifecycle email after a successful send. */
final class RecurringLifecycleEmailScheduler
{
    public function __construct(private readonly PDO $pdo, private readonly PlatformSettingsRepository $settings)
    {
    }

    /** @param array<string,mixed> $sent */
    public function queueNext(array $sent): ?int
    {
        $templateKey = trim((string) ($sent['template_key'] ?? ''));
        if (!str_starts_with($templateKey, 'lifecycle.')) return null;
        $path = EmailTemplateCatalog::pathForTemplateKey($templateKey);
        if ($path === null) {
            $candidate = 'lifecycle/' . substr($templateKey, strlen('lifecycle.')) . '.txt';
            $path = $candidate;
        }
        $timing = EmailTemplateCatalog::signupSchedule($this->settings, $path);
        if (!$timing['recurring'] || $timing['minutes'] < 1 || !EmailTemplateCatalog::isActive($this->settings, $path)) return null;
        $stmt = $this->pdo->prepare("INSERT INTO email_outbox (tenant_id,user_id,recipient_email,recipient_name,subject,body_text,body_html,template_key,status,available_at,created_at,updated_at) VALUES (:tenant_id,:user_id,:recipient_email,:recipient_name,:subject,:body_text,:body_html,:template_key,'queued',DATE_ADD(UTC_TIMESTAMP(), INTERVAL :minutes MINUTE),UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $stmt->bindValue('tenant_id', $sent['tenant_id'] ?? null, ($sent['tenant_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('user_id', $sent['user_id'] ?? null, ($sent['user_id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('recipient_email', (string) $sent['recipient_email']);
        $stmt->bindValue('recipient_name', $sent['recipient_name'] ?? null, ($sent['recipient_name'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('subject', (string) $sent['subject']);
        $stmt->bindValue('body_text', (string) $sent['body_text']);
        $stmt->bindValue('body_html', $sent['body_html'] ?? null, ($sent['body_html'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue('template_key', $templateKey);
        $stmt->bindValue('minutes', $timing['minutes'], PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }
}
