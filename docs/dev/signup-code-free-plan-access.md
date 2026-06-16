# Free plan access signup-code implementation

Migration `0035_signup_code_free_plan_months.sql` adds `tenant_signup_codes.free_access_months` and plan-assignment grant metadata on `tenant_plan_assignments`.

Code paths:

- `App\Platform\Signup\SignupCodeRepository::create()` accepts `free_months` and clamps `freeAccessMonths` to 1–60 months.
- `App\Http\Controllers\Platform\Admin\SignupCodesController` exposes the free access code type in Platform Admin → Signup Codes.
- `App\Http\Controllers\Platform\SignupController` shows a plan selector when the entered signup code is `free_months`.
- `App\Platform\Signup\TenantSignupService::register()` validates the selected active plan and writes the trial/free access assignment during tenant creation.
- `scripts/test/signup_code_free_plan_static.php` checks the feature wiring.

The free-access plan assignment is written inside the same transaction as tenant creation and signup-code redemption. If any part fails, the tenant, membership, plan assignment, and code redemption roll back together.

<!-- End of file. -->
