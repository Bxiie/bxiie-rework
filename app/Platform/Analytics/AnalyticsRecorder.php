<?php

declare(strict_types=1);

namespace App\Platform\Analytics;

use App\Http\Request;
use PDO;
use Throwable;

/** Records minimal analytics data without network access or schema discovery. */
final class AnalyticsRecorder
{
    public function __construct(private readonly PDO $pdo) {}

    public function record(Request $request, ?int $tenantId, string $eventType, ?string $entityType = null, ?int $entityId = null): void
    {
        try {
            $ip = $this->requestIp($request);
            $location = $this->headerLocation($request);
            $stmt = $this->pdo->prepare('INSERT INTO analytics_events (tenant_id,event_type,path,referrer,ip_hash,user_agent,entity_type,entity_id,country,region,city,created_at) VALUES (:tenant_id,:event_type,:path,:referrer,:ip_hash,:user_agent,:entity_type,:entity_id,:country,:region,:city,CURRENT_TIMESTAMP)');
            $stmt->execute([
                'tenant_id' => $tenantId, 'event_type' => $eventType, 'path' => mb_substr($request->path(),0,500),
                'referrer' => mb_substr((string)$request->server('HTTP_REFERER',''),0,1000),
                'ip_hash' => hash('sha256',$ip.'|artsfolio-analytics'),
                'user_agent' => mb_substr((string)$request->server('HTTP_USER_AGENT',''),0,1000),
                'entity_type' => $entityType, 'entity_id' => $entityId,
                'country' => $location['country'], 'region' => $location['region'], 'city' => $location['city'],
            ]);
        } catch (Throwable) {
            // Analytics must never break a public request.
        }
    }

    private function requestIp(Request $request): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
            $value=trim((string)$request->server($key,''));
            if ($value !== '') return trim(explode(',',$value)[0]);
        }
        return '';
    }

    private function headerLocation(Request $request): array
    {
        return [
            'country' => mb_substr(trim((string)($request->server('HTTP_CF_COUNTRY') ?: $request->server('HTTP_CF_IPCOUNTRY',''))),0,120),
            'region' => mb_substr(trim((string)($request->server('HTTP_CF_REGION') ?: $request->server('HTTP_X_GEO_REGION',''))),0,120),
            'city' => mb_substr(trim((string)($request->server('HTTP_CF_IPCITY') ?: $request->server('HTTP_X_GEO_CITY',''))),0,120),
        ];
    }
}

// End of file.
