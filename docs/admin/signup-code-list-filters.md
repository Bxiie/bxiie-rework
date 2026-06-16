# Signup code list filters

Platform admins manage signup codes from **Platform Admin → Signup Codes**.

The list hides used and revoked codes by default so active codes stay easy to find.
Use **Signup code list options** to show either category:

- **Show used codes** displays codes that have reached their redemption limit.
- **Show revoked codes** displays codes manually revoked by a platform admin.

The selected options persist in the current browser through secure cookies scoped to the signup-code page.

A code becomes `used` when its redemption count reaches its redemption limit. Older rows that used the previous `redeemed` status are migrated to `used`.

Revoked codes remain `revoked` even if they have prior usage. Revoked and used codes cannot be used for signup or tenant billing redemption because only `active` codes validate.

<!-- End of file. -->
