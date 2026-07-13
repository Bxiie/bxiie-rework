# Tenant password-reset guard

The tenant `POST /password/forgot` route captures the request-scoped
`TenantPasswordResetGuard` created by `AppKernel`.

The guard verifies that the submitted email belongs to a user associated with
the current tenant before a tenant-scoped password-reset token and email are
created.

Without the closure capture, submitting the form calls `recipientExists()` on
an undefined variable and returns HTTP 500.

<!-- End of file. -->
