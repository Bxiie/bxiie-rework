<?php

declare(strict_types=1);

namespace App\Support\Flash;

/**
 * Tiny session-backed flash message helper for browser admin screens.
 */
final class FlashMessages
{
    private const KEY = '_artsfolio_flash_messages';

    public static function add(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[self::KEY][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function consumeHtml(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        if (!$messages) {
            return '';
        }

        $html = '<div class="admin-flashes">';

        foreach ($messages as $message) {
            $type = htmlspecialchars((string) ($message['type'] ?? 'info'), ENT_QUOTES, 'UTF-8');
            $text = htmlspecialchars((string) ($message['message'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<p class="admin-flash admin-flash-' . $type . '">' . $text . '</p>';
        }

        return $html . '</div>';
    }
}

// End of file.
