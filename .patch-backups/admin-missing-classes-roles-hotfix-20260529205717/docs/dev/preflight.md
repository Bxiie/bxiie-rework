# Preflight Checks

## Scope

The preflight script runs syntax checks, migration integrity checks, and local smoke tests before commits.

## Command

```bash
cd /Users/bxiie/Dropbox/tcdev/artsfolio
./scripts/test/preflight.sh
```

## Current coverage

```text
PHP syntax checks
shell syntax checks
migration integrity
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
audit log write/search
platform admin role/list/settings/audit checks
tenant admin role/list/settings/audit checks
contact/signup persistence
contact status actions
email signup consent actions
CSV response generation
HTTP smoke tests when available
public contact/signup route smoke tests when available
```

## Behavior

Missing optional smoke test scripts are skipped instead of failing preflight.

## Assumptions

```text
local MariaDB Docker container is running
migrations have been applied
seed data exists for bxiie tenant and roles
```

<!-- End of file. -->
