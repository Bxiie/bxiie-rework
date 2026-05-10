<?php
/**
 * Privacy-conscious usage tracking for admin analytics.
 */

declare(strict_types=1);

namespace App\Services;

use PDO;

final class StatsTracker
{
    public function __construct(private PDO $db)
    {
    }

    public function hit(int $tenantId, string $type, ?int $imageId = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $ref = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);
        $path = substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', 0, 500);
        $country = substr($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '', 0, 2);

        $stmt = $this->db->prepare('INSERT INTO page_views (tenant_id, event_type, image_id, path, referrer, user_agent, ip_hash, country_code, created_at) VALUES (:tenant_id, :event_type, :image_id, :path, :referrer, :user_agent, :ip_hash, :country_code, datetime("now"))');
        $stmt->execute([
            'tenant_id' => $tenantId,
            'event_type' => $type,
            'image_id' => $imageId,
            'path' => $path,
            'referrer' => $ref,
            'user_agent' => $ua,
            'ip_hash' => hash('sha256', $ip . '|bxiie-stats'),
            'country_code' => $country,
        ]);
    }
}

// End of file.
