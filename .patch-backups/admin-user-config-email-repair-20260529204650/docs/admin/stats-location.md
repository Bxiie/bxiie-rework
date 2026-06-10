# Stats location data

ArtsFolio stores coarse location fields with each analytics event when a location signal is available.

Stored fields:

- `country`
- `region`
- `city`

Raw IP addresses are not stored in analytics events. The system stores only the existing anonymized `ip_hash` and, when needed, a cached location row keyed by that hash.

Location data can come from:

1. trusted proxy or edge headers, such as Cloudflare or load-balancer geo headers;
2. the local `analytics_ip_locations` cache;
3. a short public-IP lookup against ip-api.com when no header/cache location exists.

Local development requests from `127.0.0.1`, private LAN addresses, and reserved addresses generally will not have location fields.

Run this diagnostic after deployment:

```bash
php scripts/debug/check_analytics_location_contract.php
```

# End of file.
