<?php
/**
 * Minimal server-side view renderer.
 */

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../Views/' . $template . '.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function html(?string $value): string
    {
        // Admin-entered site content is intentionally allowed to contain HTML.
        // Only trusted admin users should be able to edit these fields.
        return $value ?? '';
    }
}

// End of file.
