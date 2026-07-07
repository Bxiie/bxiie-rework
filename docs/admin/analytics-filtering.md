# Analytics filtering for tenant admins

Tenant analytics are intended to show meaningful public visitor activity, not every HTTP request that reaches the site.

The analytics recorder filters obvious automated and non-public traffic before inserting rows into `analytics_events`. Filtered traffic includes crawler user agents, command-line clients, known preview/fetch agents, scanner-style clients, blank user agents, admin routes, login/logout routes, assets, media files, robots.txt, sitemap.xml, and favicon requests.

This keeps dashboard labels such as Hits, Locations, Top artwork views, and Unique IPs closer to what an artist expects when reviewing public site traffic. Raw operational traffic should be reviewed in the web server logs rather than tenant analytics.

Existing historical bot rows remain in `analytics_events` until manually deleted or aged out by retention policy. After deleting historical rows, rebuild analytics rollups for the affected window with `php scripts/maintenance/rebuild_analytics_rollups.php --days=30`.

<!-- End of file. -->
