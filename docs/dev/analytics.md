# Analytics architecture

Public requests write one minimal local `analytics_events` row through `AnalyticsRecorder`. The request path never performs external geolocation or information-schema discovery. Location comes only from trusted proxy/edge headers.

`analytics.rollup` runs through the background worker every five minutes and rebuilds the recent hourly and daily projections. Run a manual rebuild with:

```bash
php scripts/maintenance/rebuild_analytics_rollups.php 30
```

Raw events remain available for exact IP drill-down and debugging. Dashboard queries should migrate to rollups as their report shapes are stabilized.

<!-- End of file. -->
