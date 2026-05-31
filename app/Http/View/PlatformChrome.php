<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Membership\MembershipRepository;
use App\Platform\Membership\Roles;
use App\Support\Database;
use Throwable;

/**
 * Shared public-platform chrome helpers.
 *
 * This keeps the platform footer, admin link, and public header behavior
 * consistent across marketing, pricing, directory, help, and admin pages.
 */
final class PlatformChrome
{
    /**
     * Returns the configured platform copyright string with a safe default.
     */
    public static function copyrightLine(): string
    {
        $year = date('Y');
        $value = self::setting('platform_footer_copyright_html', '© ' . $year . ' ArtsFolio');
        $value = str_replace(['{year}', '{{year}}'], $year, $value);

        return self::allowSafeInlineHtml($value);
    }

    /**
     * Returns the platform-admin link only when the signed-in user has a platform role.
     */
    public static function platformAdminLink(): string
    {
        $currentUser = $GLOBALS['artsfolio_current_user'] ?? null;
        if (!is_array($currentUser) || empty($currentUser['user_id'])) {
            return '';
        }

        try {
            $pdo = Database::connect(dirname(__DIR__, 3));
            $roles = (new MembershipRepository($pdo))->platformRolesForUser((int) $currentUser['user_id']);
            $allowed = [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT, 'owner', 'admin'];
            if (array_intersect($allowed, $roles) !== []) {
                $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
                $host = preg_replace('/:\d+$/', '', $host) ?? $host;
                $platformHosts = ['artsfol.io', 'www.artsfol.io'];

                if (!in_array($host, $platformHosts, true)) {
                    return '<a class="platform-admin-top-link" href="/admin">Admin</a>';
                }

                return '<a class="platform-admin-top-link" href="/platform/admin">Admin</a>';
            }
        } catch (Throwable) {
            return '';
        }

        return '';
    }

    /**
     * Returns a CSS variable controlling public directory thumbnail size.
     */
    public static function directoryThumbnailStyle(): string
    {
        $size = self::setting('platform_directory_thumbnail_size', '180');
        $size = preg_replace('/[^0-9]/', '', $size) ?: '180';
        $size = max(80, min(420, (int) $size));

        return '--directory-thumb-size:' . $size . 'px;';
    }

    /**
     * Looks up a platform setting without forcing callers to carry a repository.
     */
    public static function setting(string $key, string $default = ''): string
    {
        try {
            $pdo = Database::connect(dirname(__DIR__, 3));
            $stmt = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :setting_key LIMIT 1');
            $stmt->execute(['setting_key' => $key]);
            $value = $stmt->fetchColumn();

            return $value === false ? $default : (string) $value;
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * Allows only small inline formatting in administrator-controlled footer text.
     */
    private static function allowSafeInlineHtml(string $html): string
    {
        $html = strip_tags($html, '<a><strong><em><span><br>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*([\'\"])javascript:[^\'\"]*\1/i', 'href="#"', $html) ?? $html;

        return $html;
    }
}

// End of file.
