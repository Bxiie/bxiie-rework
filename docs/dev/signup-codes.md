# Tenant signup-code implementation

Signup-code persistence lives in `tenant_signup_codes`, created by migration `0022_signup_codes_and_topbar.sql`.

`App\Platform\Signup\SignupCodeRepository` validates and redeems codes. `TenantSignupService` accepts optional `PlatformSettingsRepository` and `SignupCodeRepository` dependencies. Public signup routes in `public/index.php` pass those dependencies so the platform setting `tenant_signup_code_required` can gate tenant creation.

Redemption is recorded in the same transaction as tenant creation. The code row stores recipient email, redemption count, first redeemed tenant, redeemed email, and timestamps.

Platform admin UI is implemented by `App\Http\Controllers\Platform\Admin\SignupCodesController` at `/platform/admin/signup-codes`.

# End of file.
