#!/bin/bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${PROJECT_ROOT}"

prefix_lines() {
  local prefix="$1"
  local content="$2"

  if [ -z "$content" ]; then
    return 0
  fi

  while IFS= read -r line || [ -n "$line" ]; do
    printf '%s %s\n' "$prefix" "$line"
  done <<< "$content"
}

run_command() {
  local label="$1"
  shift

  local output
  local status

  set +e
  output="$("$@" 2>&1)"
  status=$?
  set -e

  if [ "$status" -eq 0 ]; then
    if [ -n "$output" ]; then
      prefix_lines "[PASS]" "$output"
    else
      printf '[PASS] %s\n' "$label"
    fi
    return 0
  fi

  if [ -n "$output" ]; then
    prefix_lines "[FAIL]" "$output"
  fi
  printf '[FAIL] %s (exit %s)\n' "$label" "$status"
  return "$status"
}

run_php() {
    local output
    local status

    printf '[RUN] php %s\n' "$*"

    set +e
    output="$(
        php \
            -d error_reporting=E_ALL \
            -d display_errors=1 \
            "$@" 2>&1
    )"
    status=$?
    set -e

    if [[ $status -ne 0 ]]; then
        if [[ -n "$output" ]]; then
            printf '%s\n' "$output" | sed 's/^/[FAIL] /'
        fi
        printf '[FAIL] php %s (exit %d)\n' "$*" "$status"
        return "$status"
    fi

    if printf '%s\n' "$output" | grep -Eiq '(^|[[:space:]])(PHP )?(Warning|Notice|Deprecated|Strict Standards|Parse error|Fatal error):'; then
        if [[ -n "$output" ]]; then
            printf '%s\n' "$output" | sed 's/^/[FAIL] /'
        fi
        printf '[FAIL] php %s emitted PHP diagnostics.\n' "$*"
        return 1
    fi

    if [[ -n "$output" ]]; then
        printf '%s\n' "$output" | sed 's/^/[PASS] /'
    fi

    return 0
}

run_if_exists() {
  local file="$1"

  if [ -f "$file" ]; then
    run_php "$file"
  else
    printf '[PASS] Optional test skipped because it is absent: %s\n' "$file"
  fi
}

run_shell_if_exists() {
  local file="$1"

  if [ -x "$file" ]; then
    run_command "$file" "$file"
  else
    printf '[PASS] Optional shell test skipped because it is absent or not executable: %s\n' "$file"
  fi
}

section() {
  printf '[PASS] %s\n' "$1"
}

trap 'status=$?; printf "[FAIL] Preflight stopped at line %s with exit %s\n" "$LINENO" "$status" >&2; exit "$status"' ERR

run_php scripts/test/resolve_tenant.php bxiie.com

run_php scripts/test/resolve_tenant.php bxiie.artsfol.io
section '== PHP syntax checks =='

run_command "PHP syntax checks" bash -c '
  set -euo pipefail
  while IFS= read -r -d "" file; do
    php -l "$file" >/dev/null
  done < <(find app public scripts -name "*.php" ! -name "._*" -print0 | sort -z)
'

section '== Shell syntax checks =='

run_command "Shell syntax checks" bash -c '
  set -euo pipefail
  while IFS= read -r -d "" file; do
    bash -n "$file"
  done < <(find scripts -name "*.sh" ! -name "._*" -print0 | sort -z)
'

section '== Migration discipline and schema health =='

run_php scripts/test/migration_numbering_static.php

run_php scripts/database/check_migration_integrity.php

run_php scripts/database/check_schema_health.php

section '== Core smoke tests =='

run_php scripts/test/resolve_tenant.php bxiie.com >/dev/null

run_php scripts/test/resolve_tenant.php bxiie.artsfol.io >/dev/null

run_if_exists scripts/test/rate_limiter.php

run_if_exists scripts/test/require_scope.php

run_if_exists scripts/test/tenant_api_access.php

run_if_exists scripts/test/tenant_role_access.php

run_if_exists scripts/test/password_auth.php

run_php scripts/test/smtp_custom_headers.php

run_php scripts/test/tenant_background_job_handlers.php

run_php scripts/test/session_repository_no_user_status.php

run_if_exists scripts/test/password_reset.php

run_if_exists scripts/test/email_verification.php

run_if_exists scripts/test/email_sender_factory.php

run_if_exists scripts/test/email_outbox.php

if [ "${ARTSFOLIO_PREFLIGHT_SEND_EMAIL:-0}" = "1" ]; then

run_if_exists scripts/workers/email_run_once.php

else

printf '[PASS] Skipping SMTP send smoke test. Set ARTSFOLIO_PREFLIGHT_SEND_EMAIL=1 only with a safe SMTP sink.
'

fi

run_if_exists scripts/test/email_outbox_status.php

run_if_exists scripts/test/lifecycle_email_guard_static.php

run_if_exists scripts/test/signup_owner_role_and_branding_static.php
run_if_exists scripts/test/tenant_signup_slug_availability_static.php
run_if_exists scripts/test/signup_site_short_name_copy_static.php
run_php scripts/test/deleted_tenant_slug_reuse_static.php
run_php scripts/test/new_tenant_homepage_defaults_static.php

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
run_if_exists scripts/test/platform_contact_email_static.php

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
  run_command "Generate route inventory" bash -c \
    'php scripts/test/route_inventory.php > /tmp/artsfolio-route-inventory.json'
else
  printf '[PASS] Optional route inventory generator is absent; capture skipped.\n'
fi

run_php scripts/test/platform_help_copyright_static.php
run_php scripts/test/help_sidebar_contrast_static.php
run_php scripts/test/help_sidebar_inline_contrast_static.php

run_php scripts/test/smtp_reserved_recipient_guard_static.php

run_php scripts/test/platform_operations_run_detail_static.php

run_php scripts/test/platform_job_detail_route_params_static.php

section '== Auth cookie header regression =='

run_php scripts/test/auth_cookie_headers.php

section '== Tenant login context regression =='

run_php scripts/test/tenant_login_context.php

section '== Platform SMTP message stream setting regression =='

run_php scripts/test/platform_smtp_message_stream_setting.php

run_php scripts/test/tenant_login_and_invite_static.php

run_if_exists scripts/test/tenant_chrome_static.php

run_if_exists scripts/test/email_outbox_diagnostics_static.php

run_php scripts/test/platform_email_templates_admin_static.php
run_php scripts/test/email_template_signup_schedule_static.php
run_php scripts/test/platform_email_template_placeholders_static.php
run_php scripts/test/platform_email_template_status_static.php
run_php scripts/test/email_template_no_end_marker_static.php
run_php scripts/test/email_logo_branding_static.php

run_php scripts/test/branded_error_pages_static.php

run_php scripts/test/tenant_getting_started_branding_static.php
run_php scripts/test/tenant_admin_canonical_layout_static.php
run_php scripts/test/onboarding_reset_controls_static.php

run_php scripts/test/tenant_color_palettes_static.php

run_php scripts/test/tenant_typography_settings_static.php

run_php scripts/test/tenant_sidebar_readability_static.php

run_php scripts/test/branded_csrf_static.php

run_php scripts/test/email_html_logo_pipeline_static.php

run_php scripts/test/password_reset_route_email_static.php

run_if_exists scripts/test/artwork_placement_password_reset_static.php

run_php scripts/test/admin_auth_content_static.php

run_php scripts/test/smtp_custom_headers_static.php

run_php scripts/test/signup_logout_home_visibility_static.php

run_php scripts/test/auth_artwork_followup_static.php

run_php scripts/test/tenant_auth_security_static.php

run_php scripts/test/auth_domain_security_static.php

run_php scripts/test/domain_dns_and_tenant_reset_static.php

run_php scripts/test/login_jobs_ui_static.php

run_php scripts/test/platform_access_login_jobs_static.php

run_php scripts/test/pricing_ui_static.php

run_php scripts/test/pricing_dom_safe_ui_static.php

run_php scripts/test/studio_pricing_contrast_static.php

run_php scripts/test/pricing_inline_repair_static.php

run_php scripts/test/pricing_final_polish_static.php

run_php scripts/test/signup_code_revocation_and_billing_static.php

run_php scripts/test/signup_code_used_filter_static.php

run_php scripts/test/signup_code_invite_status_static.php

run_php scripts/test/signup_code_invite_free_months_static.php
run_php scripts/test/signup_invite_recipient_email_placeholder_static.php
run_php scripts/test/signup_invite_template_variants_static.php
run_php scripts/test/signup_code_invite_variant_status_static.php

run_php scripts/test/signup_complimentary_checkout_static.php
run_php scripts/test/signup_post_registration_email_static.php

run_php scripts/test/signup_code_auto_invite_static.php

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

run_php scripts/test/phase3_analytics_static.php

run_php scripts/test/phase3_rollup_bucketing_static.php

run_php scripts/test/mariadb_tmpdir_script_static.php

run_php scripts/test/platform_jobs_execution_time_static.php

run_if_exists scripts/test/artwork_ajax_pagination_static.php

run_if_exists scripts/test/artwork_placement_column_filters_static.php

run_if_exists scripts/test/portfolio_section_actions_static.php
run_if_exists scripts/test/custom_public_page_slugs_static.php
run_if_exists scripts/test/tenant_admin_artwork_contact_defaults_static.php
run_if_exists scripts/test/tenant_admin_sidebar_upload_static.php

run_if_exists scripts/test/phase6_directory_projection_static.php

run_if_exists scripts/test/directory_search_sort_static.php

run_if_exists scripts/test/phase7_sales_inventory_static.php

run_if_exists scripts/test/phase9_monitoring_static.php

run_if_exists scripts/test/background_job_concurrency_static.php

run_if_exists scripts/test/analytics_rollup_freshness_static.php

run_if_exists scripts/test/phase9_migration_discipline_static.php

run_shell_if_exists scripts/test/monitoring_service_units_static.sh

run_php scripts/test/phase8_routing_static.php

run_if_exists scripts/test/admin_attention_watermark_operations_static.php
run_if_exists scripts/test/admin_notifications_watermark_nav_static.php

echo "== Content/colors background image picker layout =="
php scripts/test/content_colors_backgrounds_image_picker_layout_static.php
echo "== Content about/contact full-width layout =="
php scripts/test/content_about_contact_full_width_static.php

run_php scripts/test/oauth_button_branding_static.php

run_php scripts/test/user_timezone_preferences_static.php

printf '[PASS] Preflight completed successfully.\n'

php scripts/test/watermark_runtime_static.php
php scripts/test/background_watermark_exclusion_static.php
php scripts/test/nav_background_watermark_exclusion_static.php
php scripts/test/presentation_media_watermark_exclusion_static.php

php scripts/test/artwork_logo_list_tools_static.php

php scripts/test/email_outbox_grid_containment_static.php

run_php scripts/test/email_outbox_utc_static.php

php scripts/test/oauth_lifecycle_tenant_nav_static.php

# End of file.
run_php scripts/test/platform_user_status_auth_static.php
run_php scripts/test/platform_operations_timezone_static.php
php scripts/test/artwork_edit_notes_grid_stock_static.php
run_php scripts/test/analytics_bot_filter_static.php

# Sales checkout shipping-contact regression.
php scripts/test/sales_shipping_contact_collection_static.php

# End of file.

# Encrypted off-site backup operations regression.
run_php scripts/test/backup_operations_static.php

# End of file.

# Platform-admin Restic credential regression.
run_php scripts/test/restic_platform_settings_static.php

# End of file.

# Backup reliability regression.
run_php scripts/test/backup_outstanding_fixes_static.php

# End of file.

# Platform backup controls regression.
php scripts/test/platform_backup_controls_static.php

# End of file.

# Email logo tokens, Stripe description, and operations-copy regression.
run_php scripts/test/email_logo_tokens_and_stripe_description_static.php

# End of file.

# Redundant backup navigation button regression.
run_php scripts/test/backup_navigation_buttons_removed_static.php

# End of file.

# Plan-specific commission and Stripe-fee regression.
run_php scripts/test/plan_commission_stripe_fees_static.php

# End of file.

# Backup page administrator-time-zone regression.
run_php scripts/test/backup_admin_timezone_static.php

# End of file.

# Dedicated Tenant Admin Onboarding tab regression.
run_php scripts/test/tenant_onboarding_tab_static.php

# End of file.

# Default Tenant Admin contrast regression.
run_php scripts/test/new_tenant_admin_contrast_static.php

# End of file.

# Public visibility and delayed mailing-list prompt regression.
run_php scripts/test/public_visibility_and_signup_prompt_static.php

# End of file.

# Operations run detail and tenant trial display regression.
run_php scripts/test/operations_run_and_trial_details_static.php

# End of file.

# New-tenant documented Custom CSS regression.
run_php scripts/test/new_tenant_custom_css_seed_static.php

# End of file.

# Platform Operations route constructor regression.
run_php scripts/test/platform_operations_route_constructor_static.php

# End of file.

# Signup-code trial duration regression.
run_php scripts/test/signup_code_trial_duration_static.php

# End of file.

# Tenant password-forgot closure dependency regression.
run_php scripts/test/tenant_password_forgot_guard_capture_static.php

# End of file.

# Tenant-aware timezone layout regression.
run_php scripts/test/tenant_timezone_layout_static.php

# End of file.

# Template source, login destination, and unknown-domain regression.
run_php scripts/test/template_login_unknown_domain_static.php

# End of file.

# Caddy ask authorization for first-level ArtsFolio subdomains.
run_php scripts/test/caddy_ask_platform_subdomains_static.php

# End of file.

# Tenant billing complimentary status and billing-start date.
run_php scripts/test/tenant_billing_comp_status_static.php

# End of file.

# Neutral default tenant CSS without repository footer markers.
run_php scripts/test/default_tenant_css_cleanup_static.php

# End of file.

# Draft-only tenant contributor workflow.
run_php scripts/test/tenant_contributor_access_static.php

# Artwork upload portfolio-section assignment.
run_php scripts/test/upload_artwork_section_assignment_static.php

# End of file.

# Authorized public preview of unpublished artwork media.
run_php scripts/test/unpublished_preview_media_static.php

# End of file.

# Clear checkbox-to-label association across admin forms.
run_php scripts/test/admin_checkbox_clarity_static.php

# End of file.

# Text and image watermark support.
run_php scripts/test/image_watermark_static.php

# Artwork section names in admin grid and unpublished portfolio shortcuts.
run_php scripts/test/artwork_grid_sections_and_preview_shortcuts_static.php

# End of file.

# Image watermark invocation and transparent compositing.
run_php scripts/test/image_watermark_render_call_static.php

# End of file.

# Public watermarking must remain active during unpublished preview.
run_php scripts/test/watermark_preview_decoupling_static.php

# End of file.

# Watermark image resolution from the saved tenant media UUID.
run_php scripts/test/watermark_image_lookup_static.php

# End of file.

# Trim transparent watermark padding before scaling.
run_php scripts/test/watermark_transparent_canvas_trim_static.php

# End of file.

# Image watermark sizing and enlargement.
run_php scripts/test/watermark_image_scale_static.php

# End of file.

# Center and vertically stack image/text watermarks.
run_php scripts/test/centered_stacked_watermarks_static.php

# End of file.

# Artwork type filter on the tenant Artworks grid.
run_php scripts/test/artworks_type_filter_static.php

# End of file.

# Event definition month/year picker.
run_php scripts/test/event_month_year_picker_static.php

# End of file.
run_php scripts/test/homepage_special_section_static.php
run_php scripts/test/public_variant_detail_homepage_edit_static.php
run_php scripts/test/homepage_unwatermarked_thumbnails_static.php
run_php scripts/test/help_sidebar_final_layer_static.php

[ -f scripts/test/email_verification_tenant_scope_static.php ] && php scripts/test/email_verification_tenant_scope_static.php

[ -f scripts/test/welcome_email_complete_tokens_static.php ] && php scripts/test/welcome_email_complete_tokens_static.php

[ -f scripts/test/email_renderer_owned_tokens_static.php ] && php scripts/test/email_renderer_owned_tokens_static.php

run_php scripts/test/platform_polish_four_pack_static.php

run_php scripts/test/email_outbox_selected_timezone_static.php
