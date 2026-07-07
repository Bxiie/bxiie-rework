<?php

declare(strict_types=1);

namespace App\Platform\Analytics;

use App\Http\Request;
use PDO;
use Throwable;

/**
 * Records first-party analytics rows for public browser traffic.
 *
 * Analytics must be useful to artists, so obvious crawler, scanner, command-line,
 * preview, and non-public application traffic is filtered before insertion. The
 * raw web server logs remain the right place to inspect that operational noise.
 */
final class AnalyticsRecorder
{
    /** @var list<string> Lowercase user-agent fragments that identify non-human traffic. */
    private const BOT_USER_AGENT_FRAGMENTS = [
        'ahrefsbot',
        'aiwebindex',
        'amazonbot',
        'applebot',
        'baiduspider',
        'barkrowler',
        'bingbot',
        'bingpreview',
        'bot',
        'builtwith',
        'bytespider',
        'ccbot',
        'censys',
        'claudebot',
        'crawler',
        'curl/',
        'dotbot',
        'duckduckbot',
        'facebookexternalhit',
        'go-http-client',
        'googlebot',
        'googleother',
        'gptbot',
        'headlesschrome',
        'httpclient',
        'internetmeasurement',
        'lighthouse',
        'masscan',
        'meta-externalagent',
        'mj12bot',
        'panscient',
        'petalbot',
        'phantomjs',
        'python-requests',
        'scrapy',
        'semrushbot',
        'serankingbacklinksbot',
        'slurp',
        'sogou',
        'spider',
        'turnitin',
        'wget',
        'yandex',
        'zgrab',
    ];

    /** @var list<string> Path prefixes that are not public visitor page views. */
    private const IGNORED_PATH_PREFIXES = [
        '/admin',
        '/api',
        '/assets/',
        '/caddy/ask',
        '/login',
        '/logout',
        '/media/',
        '/platform/admin',
        '/storage/',
    ];

    /** @var list<string> Exact non-content paths to omit from artist-facing analytics. */
    private const IGNORED_EXACT_PATHS = [
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
    ];

    public function __construct(private readonly PDO $pdo) {}

    public function record(Request $request, ?int $tenantId, string $eventType, ?string $entityType = null, ?int $entityId = null): void
    {
        if (!$this->shouldRecord($request)) {
            return;
        }

        try {
            $ip = $this->requestIp($request);
            $location = $this->headerLocation($request);
            $stmt = $this->pdo->prepare('INSERT INTO analytics_events (tenant_id,event_type,path,referrer,ip_hash,user_agent,entity_type,entity_id,country,region,city,created_at) VALUES (:tenant_id,:event_type,:path,:referrer,:ip_hash,:user_agent,:entity_type,:entity_id,:country,:region,:city,CURRENT_TIMESTAMP)');
            $stmt->execute([
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'path' => mb_substr($request->path(), 0, 500),
                'referrer' => mb_substr((string) $request->server('HTTP_REFERER', ''), 0, 1000),
                'ip_hash' => hash('sha256', $ip . '|artsfolio-analytics'),
                'user_agent' => mb_substr((string) $request->server('HTTP_USER_AGENT', ''), 0, 1000),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'country' => $location['country'],
                'region' => $location['region'],
                'city' => $location['city'],
            ]);
        } catch (Throwable) {
            // Analytics must never break a public request.
        }
    }

    private function shouldRecord(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }

        $path = $request->path();
        if (in_array($path, self::IGNORED_EXACT_PATHS, true)) {
            return false;
        }

        foreach (self::IGNORED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        $userAgent = trim((string) $request->server('HTTP_USER_AGENT', ''));
        if ($userAgent === '') {
            return false;
        }

        $normalizedUserAgent = strtolower($userAgent);
        foreach (self::BOT_USER_AGENT_FRAGMENTS as $fragment) {
            if (str_contains($normalizedUserAgent, $fragment)) {
                return false;
            }
        }

        return true;
    }

    private function requestIp(Request $request): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) $request->server($key, ''));
            if ($value !== '') {
                return trim(explode(',', $value)[0]);
            }
        }

        return '';
    }

    /** @return array{country:string,region:string,city:string} */
    private function headerLocation(Request $request): array
    {
        return [
            'country' => mb_substr(trim((string) ($request->server('HTTP_CF_COUNTRY') ?: $request->server('HTTP_CF_IPCOUNTRY', ''))), 0, 120),
            'region' => mb_substr(trim((string) ($request->server('HTTP_CF_REGION') ?: $request->server('HTTP_X_GEO_REGION', ''))), 0, 120),
            'city' => mb_substr(trim((string) ($request->server('HTTP_CF_IPCITY') ?: $request->server('HTTP_X_GEO_CITY', ''))), 0, 120),
        ];
    }
}

// End of file.
