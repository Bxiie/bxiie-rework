#!/bin/bash
set -euo pipefail

PROJECT_ROOT="/Users/bxiie/Dropbox/tcdev/artsfolio"
cd "${PROJECT_ROOT}"

echo "== PHP syntax checks =="

find app public scripts -name "*.php" -print0 | sort -z | while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done

echo "PHP syntax checks passed."

echo
echo "== Migration integrity =="
php scripts/database/check_migration_integrity.php

echo
echo "== Core smoke tests =="

php scripts/test/resolve_tenant.php bxiie.com >/dev/null
php scripts/test/resolve_tenant.php bxiie.artsfol.io >/dev/null

php scripts/test/rate_limiter.php >/dev/null
php scripts/test/require_scope.php >/dev/null
php scripts/test/tenant_api_access.php >/dev/null
php scripts/test/tenant_role_access.php >/dev/null

php scripts/test/password_auth.php >/dev/null
php scripts/test/password_reset.php >/dev/null
php scripts/test/email_verification.php >/dev/null

php scripts/test/email_sender_factory.php >/dev/null
php scripts/test/email_outbox.php >/dev/null
php scripts/workers/email_run_once.php >/dev/null
php scripts/test/email_outbox_status.php >/dev/null

php scripts/test/audit_log.php >/dev/null

echo "Core smoke tests passed."

echo
echo "Preflight passed."

# End of file.
