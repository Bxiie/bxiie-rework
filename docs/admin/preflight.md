# Preflight Administration

## Current tool

```bash
./scripts/test/preflight.sh
```

## Purpose

Preflight provides a basic local confidence check before committing platform refactor changes.

## Deployment note

This is not a replacement for CI/CD.

Before production deployment, add:

```text
CI runner
database reset test
migration up/down strategy
HTTP route smoke tests against deployed hostnames
browser tests for auth/admin flows
API tests
security checks
static analysis
```

<!-- End of file. -->
