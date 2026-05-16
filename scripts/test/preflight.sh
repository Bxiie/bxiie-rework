#!/bin/bash
set -euo pipefail

PROJECT_ROOT="/Users/bxiie/Dropbox/tcdev/artsfolio"
cd "${PROJECT_ROOT}"

run_if_exists() {
  local file="$1"

  if [ -f "$file" ]; then
    php "$file" >/dev/null
  else
    echo "Skipping missing optional test: $file"
  fi
}

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

run_if_exists scripts/test/rate_limiter.php
run_if_exists scripts/test/require_scope.php
run_if_exists scripts/test/tenant_api_access.php
run_if_exists scripts/test/tenant_role_access.php

run_if_exists scripts/test/password_auth.php
run_if_exists scripts/test/password_reset.php
run_if_exists scripts/test/email_verification.php

run_if_exists scripts/test/email_sender_factory.php
run_if_exists scripts/test/email_outbox.php
run_if_exists scripts/workers/email_run_once.php
run_if_exists scripts/test/email_outbox_status.php

run_if_exists scripts/test/audit_log.php

if [ -x scripts/test/http_smoke.sh ]; then
  ./scripts/test/http_smoke.sh >/dev/null
else
  echo "Skipping missing optional test: scripts/test/http_smoke.sh"
fi

echo "Core smoke tests passed."

echo
echo "Preflight passed."

# End of file.
