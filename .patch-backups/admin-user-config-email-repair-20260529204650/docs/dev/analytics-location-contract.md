# Analytics location contract

Public tenant page tracking writes to `analytics_events` through `App\Http\Controllers\Tenant\HomeController::track()`.

Every tracked event should include:

- `tenant_id`
- `event_type`
- `path`
- `referrer`
- `ip_hash`
- `user_agent`
- optional `entity_type`
- optional `entity_id`
- optional `country`
- optional `region`
- optional `city`

Location resolution is handled by `App\Platform\Analytics\AnalyticsLocationResolver`.

The resolver does not store raw IP addresses. It accepts the already-derived anonymized IP hash and may cache coarse location fields in `analytics_ip_locations`.

Preferred location source order:

1. request headers from trusted infrastructure;
2. `analytics_ip_locations` cache;
3. external lookup for public IP addresses.

The resolver is deliberately fail-open. Location lookup failures must never break public tenant pages or prevent analytics events from being written.

Diagnostic command:

```bash
php scripts/debug/check_analytics_location_contract.php
```

# End of file.
