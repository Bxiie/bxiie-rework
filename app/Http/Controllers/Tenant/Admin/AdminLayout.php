<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Platform\Tenancy\TenantContext;

/**
 * Shared tenant admin shell for consistent navigation, notices, and styling.
 */
final class AdminLayout
{
    public static function render(TenantContext $tenant, string $title, string $body, array $options = []): string
    {
        $pageTitle = self::escape($title);
        $tenantName = self::escape($tenant->name);
        $active = (string) ($options['active'] ?? '');
        $notice = self::noticeHtml((string) ($_GET['notice'] ?? ''));
        $error = self::errorHtml((string) ($_GET['error'] ?? ''));
        $nav = self::nav($active);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$pageTitle} | {$tenantName} Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/admin/admin.css">
</head>
<body class="admin-shell">
<header class="admin-topbar">
    <a class="admin-brand" href="/admin">{$tenantName}</a>
    <div class="admin-topbar-actions">
        <a href="/" target="_blank" rel="noopener">View site</a>
        <a href="/admin/logout">Logout</a>
    </div>
</header>
<div class="admin-frame">
    <aside class="admin-sidebar">
        {$nav}
    </aside>
    <main class="admin-main">
        <div id="admin-notices">{$notice}{$error}</div>
        <h1>{$pageTitle}</h1>
        {$body}
    </main>
</div>
</body>
</html>
HTML;
    }

    private static function nav(string $active): string
    {
        $items = [
            'dashboard' => ['/admin', 'Dashboard'],
            'site' => ['/admin/settings', 'Site settings'],
            'content' => ['/admin/content', 'Content'],
            'artworks' => ['/admin/artworks', 'Artworks'],
            'upload' => ['/admin/artwork/upload', 'Upload artwork'],
            'events' => ['/admin/events', 'Events'],
            'messages' => ['/admin/contact-messages', 'Messages'],
            'emails' => ['/admin/email-signups', 'Email list'],
            'stats' => ['/admin/stats', 'Stats'],
            'audit' => ['/admin/audit-log', 'Audit log'],
        ];

        $html = '<nav class="admin-nav">';
        foreach ($items as $key => [$href, $label]) {
            $class = $key === $active ? ' class="active"' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }
        $html .= '</nav>';

        return $html;
    }

    private static function noticeHtml(string $notice): string
    {
        $messages = [
            'saved' => 'Saved.',
            'content-saved' => 'Content saved.',
            'status-updated' => 'Artwork status updated.',
            'artwork-archived' => 'Artwork archived.',
            'event-saved' => 'Event saved.',
            'deleted' => 'Deleted.',
        ];

        if (!isset($messages[$notice])) {
            return '';
        }

        return '<p class="admin-notice success">' . self::escape($messages[$notice]) . '</p>';
    }

    private static function errorHtml(string $error): string
    {
        $messages = [
            'csrf' => 'Security check failed. Please try again.',
            'invalid' => 'The submitted data was invalid.',
            'forbidden' => 'You do not have access to that action.',
        ];

        if (!isset($messages[$error])) {
            return '';
        }

        return '<p class="admin-notice error">' . self::escape($messages[$error]) . '</p>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
