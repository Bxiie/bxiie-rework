# Preflight Checks

## Scope

The preflight script runs syntax checks, migration integrity checks, and core smoke tests before commits.

## Command

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
./scripts/test/preflight.sh
```

## Current coverage

```text
PHP syntax checks under app, public, scripts
migration integrity checker
tenant resolution
rate limiter
API scope checks
tenant API access checks
tenant role checks
password auth
password reset
email verification
email sender factory
email outbox queueing
dry-run email worker
audit log write
```

## When to run

Run before each commit during platform refactor work.

## Notes

The script assumes:

```text
local MariaDB Docker container is running
migrations have been applied
seed data exists for bxiie tenant and roles
```

<!-- End of file. -->
