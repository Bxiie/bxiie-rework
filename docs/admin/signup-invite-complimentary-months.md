# Complimentary months in prospective signup invitations

Platform administrators create prospect codes from **Platform Admin → Signup Codes**. The free-access field defaults to one month.

Individual one-time codes and shared free-access codes retain the configured complimentary period. The invitation email tells the prospect that the plan they select is free for that period and links directly to signup with the code.

After signup, `TenantSignupService` records the selected plan as a trial whenever the redeemed code has `free_access_months > 0`. The complimentary period waives ArtsFolio plan charges only. Payment-card fees, commissions, shipping, taxes, and other third-party charges are not waived.

Invite copy lives in `template/email/platform/tenant-signup-invite.txt`.

<!-- End of file. -->
