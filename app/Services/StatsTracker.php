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
        $countryCode = $this->firstHeader(['HTTP_CF_IPCOUNTRY', 'HTTP_X_GEO_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY']);
        $country = $this->firstHeader(['HTTP_CF_COUNTRY', 'HTTP_X_GEO_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY']);
        $state = $this->firstHeader(['HTTP_CF_REGION', 'HTTP_CF_REGION_CODE', 'HTTP_X_GEO_STATE', 'HTTP_X_APPENGINE_REGION']);
        $city = $this->firstHeader(['HTTP_CF_IPCITY', 'HTTP_X_GEO_CITY', 'HTTP_X_APPENGINE_CITY']);

        if ($country === '' && $countryCode !== '') {
            $country = $countryCode;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO page_views (
                tenant_id,
                event_type,
                image_id,
                path,
                referrer,
                user_agent,
                ip_hash,
                country_code,
                city,
                state,
                country,
                created_at
            ) VALUES (
                :tenant_id,
                :event_type,
                :image_id,
                :path,
                :referrer,
                :user_agent,
                :ip_hash,
                :country_code,
                :city,
                :state,
                :country,
                datetime("now")
            )'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'event_type' => $type,
            'image_id' => $imageId,
            'path' => $path,
            'referrer' => $ref,
            'user_agent' => $ua,
            'ip_hash' => hash('sha256', $ip . '|bxiie-stats'),
            'country_code' => substr($countryCode, 0, 16),
            'city' => substr($city, 0, 120),
            'state' => substr($state, 0, 120),
            'country' => substr($country, 0, 120),
        ]);
    }

    private function firstHeader(array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

// End of file.
