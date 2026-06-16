# Developer notes: signup code revocation and billing redemption

- `SignupCodeRepository::revoke()` sets `tenant_signup_codes.status = revoked`.
- `SignupCodeRepository::validateFreeAccessForExistingTenant()` reuses normal active/recipient/max-redemption validation, then enforces `code_type = free_months`.
- `Tenant\Admin\BillingController::applyFreeAccessCode()` applies an active free-month code to an existing tenant plan assignment.
- Existing-tenant free access writes `tenant_plan_assignments.status = trial`, `complimentary_until`, `granted_by_signup_code_id`, and `billing_note`.
- Routes are mounted at `/platform/admin/signup-codes/revoke` and `/admin/billing/free-access-code`.
- Static coverage lives in `scripts/test/signup_code_revocation_and_billing_static.php`.

<!-- End of file. -->
