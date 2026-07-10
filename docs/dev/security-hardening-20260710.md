# Security hardening, July 10 2026

This pass closes cross-tenant OAuth API access, fails Stripe webhooks closed, adds authentication and reset throttling, caches Caddy decisions and watermark output, enforces CSRF on placement mutations, validates slugs and hostnames, and hardens runtime defaults. APCu is optional; without it, Caddy authorization remains correct but uses only database throttling. The watermark cache directory must be writable by the PHP service account.

<!-- End of file. -->
