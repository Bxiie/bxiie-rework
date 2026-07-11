# Signup invitation email variants

Platform Admin > Email Templates exposes three independently editable and suppressible signup invitation types:

- `platform/tenant-signup-invite-no-free-period.txt`: used when free access months is `0`.
- `platform/tenant-signup-invite-one-month.txt`: used when free access months is `1`.
- `platform/tenant-signup-invite.txt`: used when free access months is `2` or more.

Each template has its own Active/Suppressed switch. Suppressing one variant affects only signup codes whose complimentary duration selects that variant.

The supported placeholders are:

- `{{ recipient_email }}`
- `{{ free_access_months }}`
- `{{ signup_code }}`
- `{{ signup_url }}`

Legacy uppercase aliases remain supported.

<!-- End of file. -->
