# Signup code revocation and tenant billing redemption

- Platform admins may revoke any signup code from Platform Admin -> Signup Codes.
- Revoked codes cannot start public signup and cannot be applied from tenant billing.
- Tenant owners may apply a free access code from Tenant Admin -> Billing.
- Existing-tenant redemption accepts only `free_months` codes and requires the selected plan to be active.
- Applying the code updates the tenant plan assignment to `trial`, sets `complimentary_until`, links `granted_by_signup_code_id`, and records a billing note.
- Free access waives only ArtsFolio platform subscription billing for the configured period. Sales commissions, card fees, shipping, and taxes still apply.

<!-- End of file. -->
