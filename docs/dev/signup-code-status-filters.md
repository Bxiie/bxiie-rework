# Signup code status and filters

`tenant_signup_codes.status` uses these operational values:

- `active` means the code can still be validated and redeemed.
- `used` means the code has reached `max_redemptions`.
- `revoked` means a platform admin manually disabled the code.

Migration `0036_signup_code_used_status.sql` converts legacy `redeemed` rows to `used`.

`SignupCodeRepository::markRedeemed()` increments `redemption_count` and changes status to `used` when the code reaches its configured limit. The method name remains `markRedeemed()` for compatibility with existing signup and billing call sites, but the persisted terminal status is `used`.

`SignupCodeRepository::listRecent()` accepts `includeUsed` and `includeRevoked` flags. Both default to `false`, keeping terminal codes out of the ordinary platform-admin list.

`Platform\Admin\SignupCodesController` stores list preferences in cookies:

- `artsfolio_signup_codes_show_used`
- `artsfolio_signup_codes_show_revoked`

The cookies are secure, HTTP-only, `SameSite=Lax`, and scoped to `/platform/admin/signup-codes`.

<!-- End of file. -->
