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
php scripts/test/smtp_custom_headers.php
php scripts/test/tenant_background_job_handlers.php
php scripts/test/session_repository_no_user_status.php
run_if_exists scripts/test/password_reset.php
run_if_exists scripts/test/email_verification.php

run_if_exists scripts/test/email_sender_factory.php
run_if_exists scripts/test/email_outbox.php

# Production preflight must not send real SMTP messages. The email outbox
# script above verifies queueing and template rendering; the actual worker send
# path is covered by service health and logs. Set this variable only when using
# a safe local SMTP sink such as MailHog.
if [ "${ARTSFOLIO_PREFLIGHT_SEND_EMAIL:-0}" = "1" ]; then
  run_if_exists scripts/workers/email_run_once.php
else
  echo "Skipping SMTP send smoke test. Set ARTSFOLIO_PREFLIGHT_SEND_EMAIL=1 only with a safe SMTP sink."
fi

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
run_if_exists scripts/test/platform_user_invite_repository_static.php
run_if_exists scripts/test/platform_settings.php

run_if_exists scripts/test/tenant_admin_role.php
run_if_exists scripts/test/tenant_settings_admin.php
run_if_exists scripts/test/production_mutation_guard.php
run_if_exists scripts/test/contact_signup_records.php
run_if_exists scripts/test/worker_dns_tenant_static.php
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


echo "== Auth cookie header regression =="
php scripts/test/auth_cookie_headers.php

echo "== Tenant login context regression =="
php scripts/test/tenant_login_context.php

echo "== Platform SMTP message stream setting regression =="
php scripts/test/platform_smtp_message_stream_setting.php

php scripts/test/tenant_login_and_invite_static.php

run_if_exists scripts/test/tenant_chrome_static.php

run_if_exists scripts/test/email_outbox_diagnostics_static.php

php scripts/test/email_logo_branding_static.php
php scripts/test/email_html_logo_pipeline_static.php
