# Custom domain DNS verification results

The Platform Admin → Domains page shows the most recent DNS verification result for each tenant domain.

The displayed result includes:

- verification status,
- time of the last check,
- actual IPv4 A records found in DNS,
- expected IPv4 addresses from `ARTSFOLIO_EXPECTED_IPV4`,
- the last worker error, when DNS verification failed unexpectedly.

DNS verification is still queued as a background job. The result is persisted on `tenant_domains` in `dns_last_checked_at`, `dns_last_result`, and `dns_last_error`.

# End of file.
