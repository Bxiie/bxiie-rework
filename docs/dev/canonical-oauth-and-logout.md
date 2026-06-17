# Canonical OAuth and logout behavior

ArtsFolio keeps Google and Facebook OAuth callbacks on the platform host so
third-party OAuth consoles only need these redirect URIs:

```text
https://artsfol.io/auth/google/callback
https://artsfol.io/auth/facebook/callback
```

Tenant login pages must link to `https://artsfol.io/auth/{provider}` with a
trusted `return_to` target pointing back to the tenant admin page. Tenant-local
`/auth/google` and `/auth/facebook` routes redirect to the same platform
entrypoint for users who manually open those URLs.

Platform `/logout` supports GET and POST. POST still validates CSRF; GET exists
for direct navigation and stale links that should sign the user out instead of
showing an invalid-CSRF page.

// End of file.
