<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Shared tenant admin layout.
 */
final class AdminLayout
{
    public static function render(string $title, string $body): string
    {
        $safeTitle = self::escape($title);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/admin/admin.css">
</head>
<body class="tenant-admin">
    <div class="tenant-admin-shell">
        <aside class="tenant-admin-sidebar">
            <a class="tenant-admin-brand" href="/admin">Bxiie</a>
            <nav>
                <a href="/admin">Dashboard</a>
                <a href="/admin/settings">Settings</a>
                <a href="/admin/content">Content</a>
                <a href="/admin/artworks">Artworks</a>
                <a href="/admin/portfolio-sections">Portfolio Sections</a>
                <a href="/admin/events">Events</a>
                <a href="/admin/contact-messages">Messages</a>
                <a href="/admin/email-signups">Email Signups</a>
                <a href="/admin/stats">Stats</a>
                <a href="/admin/audit-log">Audit Log</a>
            </nav>
            <div class="tenant-admin-sidebar-foot">
                <a href="/">View site</a>
                <a href="/logout">Logout</a>
            </div>
        </aside>
        <main class="tenant-admin-main">
            <header class="tenant-admin-topbar">
                <a href="/admin">&larr; Admin</a>
            </header>
            <section class="tenant-admin-card">
                <h1>{$safeTitle}</h1>
                {$body}
            </section>
        </main>
    </div>
</body>
</html>
HTML;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
