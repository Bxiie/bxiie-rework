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
use App\Platform\Email\EmailTemplateCatalog;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Support\Security\CsrfTokenService;

/**
 * Lists and edits existing files beneath template/email.
 *
 * Template creation and rename are constrained to safe files beneath template/email.
 */
final class EmailTemplatesController
{
    private const MAX_TEMPLATE_BYTES = 262144;

    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
        private readonly PlatformSettingsRepository $settings,
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
            'status-saved' => '<p class="admin-notice admin-notice-success">Email delivery status saved.</p>',
            'schedule-saved' => '<p class="admin-notice admin-notice-success">Signup delivery schedule saved.</p>',
            'created' => '<p class="admin-notice admin-notice-success">Email template created.</p>',
            'renamed' => '<p class="admin-notice admin-notice-success">Email template renamed.</p>',
            default => '',
        };

        $list = '';
        foreach ($templates as $relativePath => $absolutePath) {
            $selectedClass = $relativePath === $selected ? ' class="active"' : '';
            $status = EmailTemplateCatalog::isActive($this->settings, $relativePath) ? 'Active' : 'Suppressed';
            $list .= '<li><a' . $selectedClass . ' href="/platform/admin/email-templates?template=' . rawurlencode($relativePath) . '">'
                . $this->escape($relativePath) . '</a> <small>(' . $status . ')</small></li>';
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
            $description = $this->escape(EmailTemplateCatalog::description($selected));
            $isActive = EmailTemplateCatalog::isActive($this->settings, $selected);
            $checked = $isActive ? ' checked' : '';
            $statusLabel = $isActive ? 'Active' : 'Suppressed';
            $schedule = EmailTemplateCatalog::signupSchedule($this->settings, $selected);
            $scheduleForm = '';
            if (EmailTemplateCatalog::isSignupTemplate($selected)) {
                $recurringChecked = $schedule['recurring'] ? ' checked' : '';
                $scheduleForm = '<form method="post" action="/platform/admin/email-templates/schedule" style="margin-bottom:1rem">'
                    . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                    . '<input type="hidden" name="template" value="' . $this->escape($selected) . '">'
                    . '<label>Minutes after signup <input type="number" name="minutes_after_signup" min="0" max="5256000" step="1" value="' . (int) $schedule['minutes'] . '" required></label> '
                    . '<label><input type="checkbox" name="recurring" value="1"' . $recurringChecked . '> Recurring</label> '
                    . '<button type="submit">Save signup schedule</button>'
                    . '<p class="admin-muted">Recurring templates queue their next copy after each successful send, using this same interval.</p>'
                    . '</form>';
            }
            $renameForm = '<form method="post" action="/platform/admin/email-templates/rename" style="margin-bottom:1rem">'
                . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                . '<input type="hidden" name="template" value="' . $this->escape($selected) . '">'
                . '<label>Template name <input type="text" name="new_name" value="' . $this->escape(pathinfo($selected, PATHINFO_FILENAME)) . '" pattern="[A-Za-z0-9][A-Za-z0-9_-]{0,79}" required></label> '
                . '<button type="submit">Rename template</button></form>';
            $editor = '<p><strong>' . $this->escape($selected) . '</strong></p>'
                . '<p>' . $description . '</p>'
                . '<form method="post" action="/platform/admin/email-templates/status" style="margin-bottom:1rem">'
                . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                . '<input type="hidden" name="template" value="' . $this->escape($selected) . '">'
                . '<label><input type="checkbox" name="active" value="1"' . $checked . '> Active</label> '
                . '<button type="submit">Save delivery status</button>'
                . '<p class="admin-muted">Current status: <strong>' . $statusLabel . '</strong>. Suppressed templates are not added to the email outbox.</p>'
                . '</form>'
                . $scheduleForm
                . $renameForm
                . '<form method="post" action="/platform/admin/email-templates">'
                . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                . '<input type="hidden" name="template" value="' . $this->escape($selected) . '">'
                . '<p class="admin-muted">Placeholders such as <code>{{ tenant_name }}</code> are preserved until rendering. Use <code>{{logo}}</code>, <code>{{logo-large}}</code>, or <code>{{logo-small}}</code> to position an ArtsFolio logo.</p>'
                . '<label for="template_body">Template body</label>'
                . '<textarea id="template_body" name="template_body" rows="30" spellcheck="true" style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,monospace">'
                . $this->escape($body) . '</textarea>'
                . '<p><button type="submit">Save email template</button></p>'
                . '</form>';
        }

        $placeholderReference = $this->placeholderReferenceHtml();

        return Response::html(AdminLayout::render(
            title: 'Email Templates | Platform Admin',
            body: <<<HTML
{$notice}
<p class="admin-muted">Edit the filesystem-backed templates used for platform, billing, lifecycle, authentication, and sales email. Changes affect future queued email only; already queued messages retain their stored content.</p>
<div class="admin-grid" style="grid-template-columns:minmax(16rem,22rem) minmax(0,1fr);align-items:start">
    <section class="admin-panel">
        <h2>Templates</h2>
        <ul class="admin-list email-template-list">{$list}</ul>
        <hr>
        <h3>Create template</h3>
        <form method="post" action="/platform/admin/email-templates/create">
            <input type="hidden" name="csrf_token" value="{$this->escape($this->csrf->getOrCreate())}">
            <label>Folder <select name="folder"><option value="lifecycle">Lifecycle / after signup</option><option value="platform">Platform</option><option value="billing">Billing</option><option value="auth">Authentication</option><option value="sales">Sales</option></select></label>
            <label>Name <input type="text" name="name" placeholder="artist_tips" pattern="[A-Za-z0-9][A-Za-z0-9_-]{0,79}" required></label>
            <label>Format <select name="extension"><option value="txt">Text</option><option value="md">Markdown</option><option value="html">HTML</option></select></label>
            <button type="submit">Create template</button>
        </form>
    </section>
    <section class="admin-panel">
        <h2>Editor</h2>
        {$editor}
    </section>
</div>
<section class="admin-panel" style="margin-top:1rem">
    <h2>Available placeholders</h2>
    <p class="admin-muted">Use the exact <code>{{ placeholder_name }}</code> form. Availability is template-specific; unsupported placeholders remain blank or literal depending on the renderer.</p>
    {$placeholderReference}
</section>
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


    /** Updates whether a template type may be queued. */
    public function updateStatus(Request $request, ?array $currentUser): Response
    {
        if (!$this->allows($currentUser)) return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) return Response::invalidCsrf();
        $relativePath = trim((string) ($_POST['template'] ?? ''));
        $templates = $this->templateInventory();
        if ($relativePath === '' || !isset($templates[$relativePath])) return Response::html('<h1>Invalid email template</h1>', 422);
        $active = (string) ($_POST['active'] ?? '') === '1';
        $previous = EmailTemplateCatalog::isActive($this->settings, $relativePath);
        $this->settings->set(EmailTemplateCatalog::settingKey($relativePath), $active ? '1' : '0');
        if ($previous !== $active) {
            $this->auditLog->record('platform.email_template.status_updated', null, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'email_template', $relativePath, ['previous_active' => $previous, 'active' => $active, 'template_keys' => EmailTemplateCatalog::templateKeys($relativePath)], $request->server('REMOTE_ADDR'));
        }
        return new Response('', 303, ['Location' => '/platform/admin/email-templates?template=' . rawurlencode($relativePath) . '&notice=status-saved']);
    }

    public function updateSchedule(Request $request, ?array $currentUser): Response
    {
        if (!$this->allows($currentUser)) return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) return Response::invalidCsrf();
        $path = trim((string) ($_POST['template'] ?? ''));
        $templates = $this->templateInventory();
        if (!isset($templates[$path]) || !EmailTemplateCatalog::isSignupTemplate($path)) return Response::html('<h1>Invalid signup email template</h1>', 422);
        $minutes = filter_var($_POST['minutes_after_signup'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 5256000]]);
        if ($minutes === false) return Response::html('<h1>Invalid signup delay</h1><p>Enter a whole number from 0 through 5,256,000 minutes.</p>', 422);
        $recurring = (string) ($_POST['recurring'] ?? '') === '1';
        $this->settings->set(EmailTemplateCatalog::scheduleMinutesKey($path), (string) $minutes);
        $this->settings->set(EmailTemplateCatalog::scheduleRecurringKey($path), $recurring ? '1' : '0');
        $this->auditLog->record('platform.email_template.schedule_updated', null, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'email_template', $path, ['minutes_after_signup' => $minutes, 'recurring' => $recurring], $request->server('REMOTE_ADDR'));
        return new Response('', 303, ['Location' => '/platform/admin/email-templates?template=' . rawurlencode($path) . '&notice=schedule-saved']);
    }

    public function create(Request $request, ?array $currentUser): Response
    {
        if (!$this->allows($currentUser)) return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) return Response::invalidCsrf();
        $folder = (string) ($_POST['folder'] ?? '');
        $name = (string) ($_POST['name'] ?? '');
        $extension = (string) ($_POST['extension'] ?? 'txt');
        if (!in_array($folder, ['lifecycle', 'platform', 'billing', 'auth', 'sales'], true) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/', $name) || !in_array($extension, ['txt', 'md', 'html'], true)) return Response::html('<h1>Invalid template name</h1>', 422);
        $relative = $folder . '/' . $name . '.' . $extension;
        $target = $this->safeNewPath($relative);
        if (file_exists($target)) return Response::html('<h1>Template already exists</h1>', 409);
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) throw new \RuntimeException('Unable to create template directory.');
        $initial = "Subject: New ArtsFolio email\n\nWrite the email body here.\n";
        if (file_put_contents($target, $initial, LOCK_EX) === false) throw new \RuntimeException('Unable to create email template.');
        $this->auditLog->record('platform.email_template.created', null, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'email_template', $relative, [], $request->server('REMOTE_ADDR'));
        return new Response('', 303, ['Location' => '/platform/admin/email-templates?template=' . rawurlencode($relative) . '&notice=created']);
    }

    public function rename(Request $request, ?array $currentUser): Response
    {
        if (!$this->allows($currentUser)) return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) return Response::invalidCsrf();
        $old = trim((string) ($_POST['template'] ?? ''));
        $name = trim((string) ($_POST['new_name'] ?? ''));
        $templates = $this->templateInventory();
        if (!isset($templates[$old]) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/', $name)) return Response::html('<h1>Invalid template rename</h1>', 422);
        $new = dirname($old) . '/' . $name . '.' . pathinfo($old, PATHINFO_EXTENSION);
        $target = $this->safeNewPath($new);
        if (file_exists($target)) return Response::html('<h1>Template already exists</h1>', 409);
        $schedule = EmailTemplateCatalog::signupSchedule($this->settings, $old);
        $active = EmailTemplateCatalog::isActive($this->settings, $old);
        if (!rename($templates[$old], $target)) throw new \RuntimeException('Unable to rename email template.');
        $this->settings->set(EmailTemplateCatalog::settingKey($new), $active ? '1' : '0');
        $this->settings->set(EmailTemplateCatalog::scheduleMinutesKey($new), (string) $schedule['minutes']);
        $this->settings->set(EmailTemplateCatalog::scheduleRecurringKey($new), $schedule['recurring'] ? '1' : '0');
        $this->auditLog->record('platform.email_template.renamed', null, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'email_template', $new, ['previous_path' => $old], $request->server('REMOTE_ADDR'));
        return new Response('', 303, ['Location' => '/platform/admin/email-templates?template=' . rawurlencode($new) . '&notice=renamed']);
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

    private function safeNewPath(string $relative): string
    {
        $root = realpath($this->templateRoot);
        if ($root === false || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, "\0")) throw new \RuntimeException('Invalid email-template path.');
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $parent = realpath(dirname($target));
        if ($parent !== false && !str_starts_with($parent, $root . DIRECTORY_SEPARATOR) && $parent !== $root) throw new \RuntimeException('Email-template path escaped its root.');
        return $target;
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


    /**
     * Returns the documented placeholder catalog used by email renderers.
     *
     * The scope column matters: not every renderer supplies every value.
     */
    private function placeholderReferenceHtml(): string
    {
        $placeholders = [
            'logo' => ['All email templates', 'Inserts the medium ArtsFolio logo at this position in the HTML email.'],
            'logo-large' => ['All email templates', 'Inserts the large ArtsFolio logo at this position in the HTML email.'],
            'logo-small' => ['All email templates', 'Inserts the small ArtsFolio logo at this position in the HTML email.'],
            'action_url' => ['Invitation emails', 'The URL the recipient should follow to accept an invitation or complete the requested action.'],
            'admin_url' => ['Tenant lifecycle emails', 'The tenant administration dashboard URL.'],
            'amount' => ['Billing emails', 'A formatted currency amount, such as $19.00.'],
            'billing_health_url' => ['Platform billing report', 'The platform-admin billing health page URL.'],
            'billing_url' => ['Tenant billing emails', 'The tenant administration billing page URL.'],
            'cart_total' => ['Abandoned-cart emails', 'The formatted total value of the customer cart.'],
            'cart_url' => ['Abandoned-cart emails', 'The secure URL that restores and opens the customer cart.'],
            'change_type' => ['Plan-change emails', 'The billing change category, such as upgrade, downgrade, or cancel.'],
            'critical_count' => ['Platform billing report', 'The number of tenants currently in a critical billing state.'],
            'effective_at' => ['Scheduled plan-change emails', 'The date or timestamp when a scheduled billing change takes effect.'],
            'free_access_months' => ['Prospective tenant signup invitation', 'The complimentary-access duration shown in the invitation.'],
            'functions_url' => ['Tenant lifecycle emails', 'The tenant function-index documentation URL.'],
            'help_url' => ['Tenant lifecycle emails', 'The tenant help index URL.'],
            'invoice_number' => ['Billing payment emails', 'The Stripe invoice number when one is available.'],
            'invoice_url' => ['Billing payment emails', 'The Stripe-hosted invoice or payment page URL.'],
            'item_count' => ['Abandoned-cart emails', 'The number of items currently in the customer cart.'],
            'past_due_count' => ['Platform billing report', 'The number of tenant subscriptions currently past due.'],
            'plan_name' => ['Billing and signup emails', 'The human-readable selected plan name.'],
            'plan_slug' => ['Billing emails', 'The machine-readable plan identifier, such as studio or professional.'],
            'recipient_email' => ['Authentication and invitation emails', 'The recipient email address.'],
            'recipient_name' => ['Welcome and invitation emails', 'The recipient display name, or a friendly fallback when unavailable.'],
            'report_date' => ['Platform billing report', 'The date represented by the billing health report.'],
            'report_lines' => ['Platform billing report', 'The preformatted tenant-by-tenant report detail lines.'],
            'reset_url' => ['Password-reset email', 'The single-use password-reset URL.'],
            'signup_code' => ['Prospective tenant signup invitation', 'The one-time or shared signup code.'],
            'signup_url' => ['Prospective tenant signup invitation', 'The signup URL containing or associated with the code.'],
            'support_email' => ['Tenant billing emails', 'The ArtsFolio support email address.'],
            'tenant_name' => ['Tenant, billing, sales, and invitation emails', 'The public or administrative name of the tenant site.'],
            'tenant_slug' => ['Billing and tenant emails', 'The tenant site slug used in ArtsFolio URLs.'],
            'tour_url' => ['Tenant lifecycle emails', 'The guided onboarding tour URL.'],
            'verification_url' => ['Email-verification email', 'The single-use email-verification URL.'],
            'videos_url' => ['Tenant lifecycle emails', 'The tenant training-video directory URL.'],
            'warning_count' => ['Platform billing report', 'The number of tenants currently in a warning billing state.'],
        ];
        $rows = '';
        foreach ($placeholders as $name => [$scope, $meaning]) {
            $rows .= '<tr><td><code>{{ ' . $this->escape($name) . ' }}</code></td><td>Preferred</td><td>' . $this->escape($scope) . '</td><td>' . $this->escape($meaning) . '</td></tr>';
        }
        $legacyAliases = [
            '{{RECIPIENT_EMAIL}}' => ['Authentication and invitation emails', 'Legacy alias for {{ recipient_email }}.'],
            '{{FREE_ACCESS_MONTHS}}' => ['Prospective tenant signup invitation', 'Legacy alias for {{ free_access_months }}.'],
            '{{SIGNUP_CODE}}' => ['Prospective tenant signup invitation', 'Legacy alias for {{ signup_code }}.'],
            '{{SIGNUP_URL}}' => ['Prospective tenant signup invitation', 'Legacy alias for {{ signup_url }}.'],
        ];
        foreach ($legacyAliases as $token => [$scope, $meaning]) {
            $rows .= '<tr><td><code>' . $this->escape($token) . '</code></td><td>Legacy alias</td><td>' . $this->escape($scope) . '</td><td>' . $this->escape($meaning) . '</td></tr>';
        }
        return '<div style="overflow-x:auto"><table class="admin-table"><thead><tr><th>Placeholder</th><th>Form</th><th>Available in</th><th>Meaning</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
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
