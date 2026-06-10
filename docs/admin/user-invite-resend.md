# User invite resend

Tenant Admin > Users includes a `Resend invite` action for tenant users. The action queues the tenant admin invitation email again and records `tenant.user.invite_resent` in the audit log.

Platform Admin > Users includes a `Resend invite` action for platform users. The action queues the platform administrator invitation email again and records `platform.user.invite_resent` in the audit log.

The resend action does not change passwords, roles, memberships, or tenant ownership. It only queues the email and records the administrative action.

# End of file.
