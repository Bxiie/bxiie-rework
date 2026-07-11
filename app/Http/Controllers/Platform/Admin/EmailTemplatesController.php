<?php

/**
 * Platform-admin editor for filesystem-backed email templates.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Membership\Roles;
use App\Support\Security\CsrfTokenService;

/**
 * Lists and edits existing files beneath template/email.
 *
 * Template creation and arbitrary path access are intentionally unsupported.
 */
final class EmailTemplatesController
{
    private const MAX_TEMPLATE_BYTES = 262144;

    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
        private readonly string $templateRoot,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->allows($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $templates = $this->templateInventory();
        $selected = trim((string) ($_GET['template'] ?? ''));
        if ($selected === '' || !isset($templates[$selected])) {
            $selected = array_key_first($templates) ?? '';
        }

        $notice = match ((string) ($_GET['notice'] ?? '')) {
            'saved' => '<p class="admin-notice admin-notice-success">Email template saved.</p>',
            default => '',
        };

        $list = '';
        foreach ($templates as $relativePath => $absolutePath) {
            $active = $relativePath === $selected ? ' class="active"' : '';
            $list .= '<li><a' . $active . ' href="/platform/admin/email-templates?template=' . rawurlencode($relativePath) . '">'
                . $this->escape($relativePath) . '</a></li>';
        }
        if ($list === '') {
            $list = '<li>No email templates were found.</li>';
        }

        $editor = '<p class="admin-muted">Select a template to edit.</p>';
        if ($selected !== '' && isset($templates[$selected])) {
            $body = file_get_contents($templates[$selected]);
            if ($body === false) {
                return Response::html('<h1>Unable to read email template</h1>', 500);
            }

            $csrf = $this->escape($this->csrf->getOrCreate());
            $editor = '<form method="post" action="/platform/admin/email-templates">'
                . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                . '<input type="hidden" name="template" value="' . $this->escape($selected) . '">'
                . '<p><strong>' . $this->escape($selected) . '</strong></p>'
                . '<p class="admin-muted">Placeholders such as <code>{{ tenant_name }}</code> are preserved literally until the email is rendered.</p>'
                . '<label for="template_body">Template body</label>'
                . '<textarea id="template_body" name="template_body" rows="30" spellcheck="true" style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace">'
                . $this->escape($body) . '</textarea>'
                . '<p><button type="submit">Save email template</button></p>'
                . '</form>';
        }

        return Response::html(AdminLayout::render(
            title: 'Email Templates | Platform Admin',
            body: <<<HTML
{$notice}
<p class="admin-muted">Edit the filesystem-backed templates used for platform, billing, lifecycle, authentication, and sales email. Changes affect future queued email only; already queued messages retain their stored content.</p>
<div class="admin-grid" style="grid-template-columns:minmax(16rem,22rem) minmax(0,1fr);align-items:start">
    <section class="admin-panel">
        <h2>Templates</h2>
        <ul class="admin-list email-template-list">{$list}</ul>
    </section>
    <section class="admin-panel">
        <h2>Editor</h2>
        {$editor}
    </section>
</div>
HTML,
            nav: [
                '/admin' => 'Dashboard',
                '/admin/tenants' => 'Tenants',
                '/admin/email-outbox' => 'Email Outbox',
                '/admin/email-templates' => 'Email Templates',
                '/admin/audit-log' => 'Audit Log',
                '/admin/platform-settings' => 'Settings',
            ],
        ));
    }

    public function update(Request $request, ?array $currentUser): Response
    {
        if (!$this->allows($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        $relativePath = trim((string) ($_POST['template'] ?? ''));
        $templates = $this->templateInventory();
        if ($relativePath === '' || !isset($templates[$relativePath])) {
            return Response::html('<h1>Invalid email template</h1><p>The selected template is not in the approved email-template inventory.</p>', 422);
        }

        $body = (string) ($_POST['template_body'] ?? '');
        if (strlen($body) > self::MAX_TEMPLATE_BYTES) {
            return Response::html('<h1>Email template is too large</h1><p>The maximum size is 256 KiB.</p>', 422);
        }

        if (str_contains($body, "\0")) {
            return Response::html('<h1>Invalid email template content</h1>', 422);
        }

        $target = $templates[$relativePath];
        $previous = file_get_contents($target);
        if ($previous === false) {
            return Response::html('<h1>Unable to read email template</h1>', 500);
        }

        $normalized = rtrim(str_replace(["\r\n", "\r"], "\n", $body)) . "\n";
        if ($normalized !== $previous) {
            $this->atomicWrite($target, $normalized);

            $this->auditLog->record(
                'platform.email_template.updated',
                null,
                isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
                'email_template',
                $relativePath,
                [
                    'previous_sha256' => hash('sha256', $previous),
                    'new_sha256' => hash('sha256', $normalized),
                    'previous_bytes' => strlen($previous),
                    'new_bytes' => strlen($normalized),
                ],
                $request->server('REMOTE_ADDR'),
            );
        }

        return new Response('', 303, [
            'Location' => '/platform/admin/email-templates?template=' . rawurlencode($relativePath) . '&notice=saved',
        ]);
    }

    /** @return array<string,string> Relative path => absolute canonical path. */
    private function templateInventory(): array
    {
        $root = realpath($this->templateRoot);
        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('Email template directory is unavailable.');
        }

        $inventory = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink() || str_starts_with($file->getFilename(), '._')) {
                continue;
            }

            $absolutePath = $file->getRealPath();
            if ($absolutePath === false || !str_starts_with($absolutePath, $root . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            if (!in_array($extension, ['txt', 'md', 'html'], true)) {
                continue;
            }

            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root) + 1));
            $inventory[$relativePath] = $absolutePath;
        }

        ksort($inventory, SORT_NATURAL | SORT_FLAG_CASE);

        return $inventory;
    }

    private function atomicWrite(string $target, string $body): void
    {
        $directory = dirname($target);
        $temporary = tempnam($directory, '.email-template-');
        if ($temporary === false) {
            throw new \RuntimeException('Unable to create temporary email-template file.');
        }

        try {
            $mode = fileperms($target);
            if (file_put_contents($temporary, $body, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to write temporary email-template file.');
            }
            if ($mode !== false) {
                chmod($temporary, $mode & 0777);
            }
            if (!rename($temporary, $target)) {
                throw new \RuntimeException('Unable to replace email-template file.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function allows(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }

    private function escape(string $value): string
    {
        return AdminLayout::escape($value);
    }
}

// End of file.
