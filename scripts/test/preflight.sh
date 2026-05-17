#!/bin/bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${PROJECT_ROOT}"

run_if_exists() {
  local file="$1"

  if [ -f "$file" ]; then
    php "$file" >/dev/null
  else
    echo "Skipping missing optional test: $file"
  fi
}

run_shell_if_exists() {
  local file="$1"

  if [ -x "$file" ]; then
    "$file" >/dev/null
  else
    echo "Skipping missing optional shell test: $file"
  fi
}

echo "== PHP syntax checks =="

find app public scripts -name "*.php" -print0 | sort -z | while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done

echo "PHP syntax checks passed."

echo
echo "== Shell syntax checks =="

find scripts -name "*.sh" -print0 | sort -z | while IFS= read -r -d '' file; do
  bash -n "$file"
done

echo "Shell syntax checks passed."

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
run_if_exists scripts/test/audit_log_search.php
run_if_exists scripts/test/platform_audit_log_list.php
run_if_exists scripts/test/platform_settings_audit.php
run_if_exists scripts/test/tenant_settings_audit.php
run_if_exists scripts/test/tenant_admin_action_audit.php
run_if_exists scripts/test/tenant_audit_log_list.php

run_if_exists scripts/test/platform_admin_role.php
run_if_exists scripts/test/platform_admin_lists.php
run_if_exists scripts/test/platform_settings.php

run_if_exists scripts/test/tenant_admin_role.php
run_if_exists scripts/test/tenant_settings_admin.php
run_if_exists scripts/test/contact_signup_records.php
run_if_exists scripts/test/tenant_admin_lists.php
run_if_exists scripts/test/contact_message_status.php
run_if_exists scripts/test/email_signup_consent.php

run_if_exists scripts/test/csv_response.php

run_shell_if_exists scripts/test/http_smoke.sh
run_shell_if_exists scripts/test/public_contact_signup_routes.sh

if [ -f scripts/test/route_inventory.php ]; then
  php scripts/test/route_inventory.php > /tmp/artsfolio-route-inventory.json
fi

echo "Core smoke tests passed."

echo
echo "Preflight passed."

# End of file.
