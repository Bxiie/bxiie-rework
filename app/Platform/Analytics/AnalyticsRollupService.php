<?php

declare(strict_types=1);

namespace App\Platform\Analytics;

use PDO;

/** Rebuilds recent analytics rollups from raw immutable events. */
final class AnalyticsRollupService
{
    public function __construct(private readonly PDO $pdo) {}

    public function rebuildRecent(int $days = 3): array
    {
        $days=max(1,min(365,$days));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM analytics_rollups_hourly WHERE bucket_start >= DATE_SUB(CURRENT_DATE, INTERVAL {$days} DAY)");
            $this->pdo->exec("DELETE FROM analytics_rollups_daily WHERE bucket_date >= DATE_SUB(CURRENT_DATE, INTERVAL {$days} DAY)");
            $hourly=$this->pdo->exec("INSERT INTO analytics_rollups_hourly (bucket_start,tenant_key,event_type,path,entity_type,entity_id,country,region,city,dimension_hash,event_count,unique_visitor_count,first_event_at,last_event_at) SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00'),COALESCE(tenant_id,0),event_type,COALESCE(path,''),COALESCE(entity_type,''),COALESCE(entity_id,0),COALESCE(country,''),COALESCE(region,''),COALESCE(city,''),SHA2(CONCAT_WS('\x1F',COALESCE(path,''),COALESCE(entity_type,''),COALESCE(entity_id,0),COALESCE(country,''),COALESCE(region,''),COALESCE(city,'')),256),COUNT(*),COUNT(DISTINCT ip_hash),MIN(created_at),MAX(created_at) FROM analytics_events WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL {$days} DAY) GROUP BY 1,2,3,4,5,6,7,8,9");
            $daily=$this->pdo->exec("INSERT INTO analytics_rollups_daily (bucket_date,tenant_key,event_type,path,entity_type,entity_id,country,region,city,dimension_hash,event_count,unique_visitor_count,first_event_at,last_event_at) SELECT DATE(created_at),COALESCE(tenant_id,0),event_type,COALESCE(path,''),COALESCE(entity_type,''),COALESCE(entity_id,0),COALESCE(country,''),COALESCE(region,''),COALESCE(city,''),SHA2(CONCAT_WS('\x1F',COALESCE(path,''),COALESCE(entity_type,''),COALESCE(entity_id,0),COALESCE(country,''),COALESCE(region,''),COALESCE(city,'')),256),COUNT(*),COUNT(DISTINCT ip_hash),MIN(created_at),MAX(created_at) FROM analytics_events WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL {$days} DAY) GROUP BY 1,2,3,4,5,6,7,8,9");
            $this->pdo->commit();
            return ['hourly_rows'=>(int)$hourly,'daily_rows'=>(int)$daily,'days'=>$days];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }
}

// End of file.
