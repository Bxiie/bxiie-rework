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
echo "== Migration discipline and schema health =="
php scripts/test/migration_numbering_static.php
php scripts/database/check_migration_integrity.php
php scripts/database/check_schema_health.php

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
run_if_exists scripts/test/lifecycle_email_guard_static.php
run_if_exists scripts/test/signup_owner_role_and_branding_static.php

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
run_if_exists scripts/test/platform_user_lifecycle_static.php
run_if_exists scripts/test/platform_settings.php

run_if_exists scripts/test/tenant_admin_role.php
run_if_exists scripts/test/tenant_settings_admin.php
run_if_exists scripts/test/production_mutation_guard.php
run_if_exists scripts/test/contact_signup_records.php
run_if_exists scripts/test/contact_email_notification_static.php
run_if_exists scripts/test/platform_contact_management_static.php
run_if_exists scripts/test/turnstile_captcha_static.php
run_if_exists scripts/test/tenant_custom_captcha_static.php
run_if_exists scripts/test/email_signup_duplicate_notification_static.php
run_if_exists scripts/test/worker_dns_tenant_static.php
run_if_exists scripts/test/phase4_worker_scaling_static.php
run_if_exists scripts/test/worker_service_units_static.sh
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

php scripts/test/platform_help_copyright_static.php

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
php scripts/test/branded_error_pages_static.php
php scripts/test/tenant_getting_started_branding_static.php
php scripts/test/tenant_color_palettes_static.php
php scripts/test/tenant_typography_settings_static.php
php scripts/test/tenant_sidebar_readability_static.php
php scripts/test/branded_csrf_static.php
php scripts/test/email_html_logo_pipeline_static.php
php scripts/test/password_reset_route_email_static.php
run_if_exists scripts/test/artwork_placement_password_reset_static.php

php scripts/test/admin_auth_content_static.php

php scripts/test/smtp_custom_headers_static.php
php scripts/test/signup_logout_home_visibility_static.php
php scripts/test/auth_artwork_followup_static.php
php scripts/test/tenant_auth_security_static.php
php scripts/test/auth_domain_security_static.php
php scripts/test/domain_dns_and_tenant_reset_static.php
php scripts/test/login_jobs_ui_static.php
php scripts/test/platform_access_login_jobs_static.php

php scripts/test/pricing_ui_static.php
php scripts/test/pricing_dom_safe_ui_static.php
php scripts/test/studio_pricing_contrast_static.php

php scripts/test/pricing_inline_repair_static.php

php scripts/test/pricing_final_polish_static.php
php scripts/test/signup_code_revocation_and_billing_static.php
php scripts/test/signup_code_used_filter_static.php
php scripts/test/signup_code_invite_status_static.php
run_if_exists scripts/test/platform_terms_privacy_static.php
run_if_exists scripts/test/canonical_oauth_logout_static.php
run_if_exists scripts/test/oauth_signup_email_lock_static.php
run_if_exists scripts/test/tenant_settings_section_save_static.php
run_if_exists scripts/test/tenant_settings_subpages_static.php
run_if_exists scripts/test/tenant_directory_settings_subpage_static.php
run_if_exists scripts/test/tenant_contact_single_signup_static.php

run_if_exists scripts/test/tenant_directory_thumbnail_artworks_static.php
run_if_exists scripts/test/scale_fixture_static.php
run_if_exists scripts/test/media_variants_static.php
run_if_exists scripts/test/platform_scale_tenants_static.php
run_if_exists scripts/test/tenant_settings_snapshot_static.php
run_if_exists scripts/test/phase5_artwork_catalog_static.php
run_if_exists scripts/test/artwork_page_size_controls_static.php

php scripts/test/phase3_analytics_static.php
php scripts/test/phase3_rollup_bucketing_static.php
php scripts/test/mariadb_tmpdir_script_static.php
php scripts/test/platform_jobs_execution_time_static.php
run_if_exists scripts/test/artwork_ajax_pagination_static.php
run_if_exists scripts/test/artwork_placement_column_filters_static.php
run_if_exists scripts/test/portfolio_section_actions_static.php
run_if_exists scripts/test/phase6_directory_projection_static.php
run_if_exists scripts/test/directory_search_sort_static.php
run_if_exists scripts/test/phase7_sales_inventory_static.php
run_if_exists scripts/test/phase9_monitoring_static.php
run_if_exists scripts/test/background_job_concurrency_static.php
run_if_exists scripts/test/analytics_rollup_freshness_static.php
run_if_exists scripts/test/phase9_migration_discipline_static.php
run_shell_if_exists scripts/test/monitoring_service_units_static.sh

php scripts/test/phase8_routing_static.php

run_if_exists scripts/test/admin_attention_watermark_operations_static.php

php scripts/test/platform_help_copyright_static.php

# End of file.

php scripts/test/oauth_button_branding_static.php

php scripts/test/platform_help_copyright_static.php

# End of file.
