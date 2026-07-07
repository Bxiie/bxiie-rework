# Analytics bot and noise filtering

`App\Platform\Analytics\AnalyticsRecorder` is the single insertion point for first-party analytics rows. It now performs pre-insert filtering so the database does not collect obvious crawler, scanner, command-line, preview, or non-public route noise as artist-facing visitor analytics.

Filtering rules are intentionally conservative and deterministic:

- only `GET` requests are recorded;
- blank user agents are ignored;
- known bot and scanner user-agent fragments are ignored;
- `/admin`, `/platform/admin`, `/login`, `/logout`, `/assets/`, `/media/`, `/storage/`, `/api`, `/caddy/ask`, `/favicon.ico`, `/robots.txt`, and `/sitemap.xml` are ignored.

The filter is source-code based and does not perform reverse DNS or network calls. That keeps public requests fast and prevents analytics from becoming a request-time dependency on outside services.

Regression coverage lives in `scripts/test/analytics_bot_filter_static.php` and is wired into `scripts/test/preflight.sh`.

When cleaning old bot rows from production, delete from raw `analytics_events` first, then rebuild affected rollups:

```bash
php scripts/maintenance/rebuild_analytics_rollups.php --days=30
```

// End of file.
