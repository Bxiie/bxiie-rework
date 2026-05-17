<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Renders simple shared admin HTML layout.
 */
final class AdminLayout
{
    /**
     * @param array<string, string> $nav
     */
    public static function render(string $title, string $body, array $nav = []): string
    {
        $safeTitle = self::escape($title);
        $navHtml = '';

        foreach ($nav as $href => $label) {
            $navHtml .= '<a href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }

        if ($navHtml !== '') {
            $navHtml = '<nav class="admin-nav">' . $navHtml . '</nav>';
        }

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin">
    <main class="admin-shell">
        <header class="admin-header">
            <h1>{$safeTitle}</h1>
        </header>
        {$navHtml}
        <section class="admin-card">
{$body}
        </section>
    </main>
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
