# Tenant login and admin invites

Tenant `/login` must create a `user_sessions` row with the current tenant ID and return the browser `Set-Cookie` header on the redirect to `/admin`.

`App\Http\Support\SessionCookie` intentionally keeps both `issueHeader()` and the older `issueSetCookie()` aliases so rolling deployments do not break tenant login while old controllers and new helpers overlap.

Tenant admin invites are initiated from `/admin/users`. The invite flow creates or reuses a `users` row, upserts an `invited` `tenant_memberships` row, assigns the tenant `admin` role, writes an audit event, and queues an `email_outbox` message using template key `tenant_admin_invite`.

Run:

```bash
php scripts/test/tenant_login_and_invite_static.php
./scripts/test/preflight.sh
```

# End of file.
