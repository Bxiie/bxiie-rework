# Platform signup codes

Platform Admin → Signup Codes manages tenant-creation passcodes.

Use one-time codes when the invitation is for a specific prospect. A one-time code may be locked to one recipient email address and is marked redeemed after the first successful tenant signup.

Use blanket codes when a campaign, gallery list, or cohort should share one passcode. Set the redemption limit before sending the code.

Platform Settings includes `Require a signup passcode to create new tenant sites`. When enabled, `/signup` blocks tenant creation unless the submitted code is active, within its redemption limit, and either unrestricted or assigned to the submitted email address.

Bulk invites accept one email address per line and can create individual one-time codes or a shared blanket code. Invites are queued through `email_outbox` with template key `platform.tenant_signup_invite`.

# End of file.
