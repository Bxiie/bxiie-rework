# Tenant Auth Security

Tenant logout must revoke both browser cookies and the server-side `user_sessions` row. The logout controller reads the raw `artsfolio_session` cookie and passes it to `PasswordAuthService::logoutSessionToken()`, which hashes it through `SessionTokenService` and revokes it through `SessionRepository`.

Tenant password reset is tenant-scoped. Tenant reset requests only create tokens when the email belongs to an active member of the tenant. Tenant reset submissions also verify that the token user still belongs to the tenant before changing the password.

Run `php scripts/test/tenant_auth_security_static.php` after auth changes.

# End of file.
